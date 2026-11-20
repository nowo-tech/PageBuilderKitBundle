<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Event\PageMetadataSavedEvent;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\Host\HostNote;
use Nowo\TransactionDoctrine\HostFlushProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function crc32;
use function dechex;
use function dirname;
use function restore_error_handler;
use function set_error_handler;
use function str_pad;

use const E_USER_DEPRECATED;
use const STR_PAD_LEFT;

/**
 * 1.19 (REQ-ARCH-001 rule 6 deprecation layer): the host-flush probe right before each bundle flush of the host's
 * manager. One deprecation per application entity class and unit of work; the bundle's own entities are never
 * reported; without the probe (private manager, dedicated manager, DDD host) nothing is reported.
 */
#[CoversClass(PageBuilderTransaction::class)]
final class PageBuilderTransactionHostFlushProbeTest extends TestCase
{
    private const string NOTICE = 'Your pending changes to ' . HostNote::class . ' were flushed by NowoPageBuilderKitBundle; 2.0 no longer does.';

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        $this->deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only: restored right after)
        set_error_handler(function (int $type, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testAHostFlushWithAForeignPendingEntityTriggersOneDeprecationPerClassAndUnit(): void
    {
        $em          = $this->entityManager();
        $transaction = new PageBuilderTransaction($em, hostFlushProbe: new HostFlushProbe());

        $em->persist(new HostNote('first'));
        $em->persist($this->page('home'));
        $transaction->commit(new PageMetadataSavedEvent('home', 'u-home'));
        $em->persist(new HostNote('second'));
        $transaction->commit();

        self::assertSame([self::NOTICE], $this->deprecations, 'once per class and unit; the bundle\'s own page is not reported');
        self::assertSame(2, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'), '1.x: the host changes are flushed');

        // A new unit of work (the outermost boundary) reports again.
        $transaction->onBoundary();
        $em->persist(new HostNote('third'));
        $transaction->commit();
        self::assertSame([self::NOTICE, self::NOTICE], $this->deprecations);

        // A nested boundary is not a new unit.
        $transaction->onBoundary(false);
        $em->persist(new HostNote('fourth'));
        $transaction->commit();
        self::assertCount(2, $this->deprecations);

        // reset() (kernel.reset) is one too.
        $transaction->reset();
        $em->persist(new HostNote('fifth'));
        $transaction->commit();
        self::assertCount(3, $this->deprecations);
    }

    public function testAWriteUnitReportsTheChangesMadeDuringItsOperation(): void
    {
        $em          = $this->entityManager();
        $transaction = new PageBuilderTransaction($em, hostFlushProbe: new HostFlushProbe());

        $transaction->run(static function () use ($em): void {
            $em->persist(new BuilderPage()->setPageKey('unit')->setUuid('00000000-0000-4000-8000-0000000000aa'));
            // Host code called during the unit (a listener, a provider) that leaves a change pending.
            $em->persist(new HostNote('during the unit'));
        });

        self::assertSame([self::NOTICE], $this->deprecations);
    }

    public function testOnlyBundleEntitiesPendingTriggerNothing(): void
    {
        $em          = $this->entityManager();
        $transaction = new PageBuilderTransaction($em, hostFlushProbe: new HostFlushProbe());

        $em->persist($this->page('only-bundle'));
        $transaction->commit();

        self::assertSame([], $this->deprecations);
    }

    public function testWithoutTheProbeNothingIsReported(): void
    {
        $em          = $this->entityManager();
        $transaction = new PageBuilderTransaction($em);

        $em->persist(new HostNote('private or dedicated mode'));
        $transaction->commit();

        self::assertSame([], $this->deprecations);
    }

    public function testAFailingProbeIsLoggedAndNeverBreaksTheWrite(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willThrowException(new RuntimeException('broken unit of work'));
        $em->expects(self::once())->method('flush');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Page builder: the host-flush probe failed; the write goes on.', self::callback(static fn (array $context): bool => $context['exception'] === RuntimeException::class));

        new PageBuilderTransaction($em, logger: $logger, hostFlushProbe: new HostFlushProbe())->commit();
    }

    private function page(string $key): BuilderPage
    {
        return new BuilderPage()->setPageKey($key)->setUuid('00000000-0000-4000-8000-' . str_pad(dechex(crc32($key)), 12, '0', STR_PAD_LEFT));
    }

    private function entityManager(): EntityManagerInterface
    {
        $root   = dirname(__DIR__, 3);
        $config = ORMSetup::createAttributeMetadataConfiguration([$root . '/src/Entity', $root . '/tests/Fixtures/Host'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $em         = new EntityManager($connection, $config);
        new SchemaTool($em)->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }
}
