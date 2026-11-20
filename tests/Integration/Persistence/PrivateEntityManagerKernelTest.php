<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PagePublisherInterface;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\Host\HostNote;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function dirname;
use function md5;
use function memory_get_usage;
use function microtime;
use function preg_replace_callback;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * 1.19 (REQ-ARCH-001 rule 6): `doctrine.private_entity_manager: true` through a real kernel (SQLite), and the host-flush
 * probe of the default path. The application's pending changes stay pending in private mode; the private manager is
 * dropped at every boundary of a kept-alive kernel (FrankenPHP worker mode with kernel reset off, no services
 * resetter, no NowoTransactionDoctrineBundle) and replaced when a failed write closed it.
 */
final class PrivateEntityManagerKernelTest extends TestCase
{
    private const string NOTICE = 'Your pending changes to ' . HostNote::class . ' were flushed by NowoPageBuilderKitBundle; 2.0 no longer does.';

    private ?PersistenceTestKernel $kernel = null;

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only: restored right after)
        set_error_handler(function (int $type, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testPrivateModeKeepsTheApplicationsPendingChangesPending(): void
    {
        $container = $this->boot(['private_entity_manager' => true]);
        $default   = $this->defaultManager($container);
        $private   = $container->get('nowo_page_builder_kit.entity_manager');
        self::assertInstanceOf(PrivateEntityManager::class, $private);
        self::assertTrue($container->has(OwnEntityManagerSubscriber::class));

        $stored = new HostNote('stored');
        $default->persist($stored);
        $default->flush();
        $stored->text = 'edited, not flushed';
        $pending      = new HostNote('pending insert');
        $default->persist($pending);

        $publisher = $this->publisher($container);
        $publisher->createPageByKey('home', 'Home', 'en');
        $publisher->publishByKey('home');
        $documents = $container->get(DocumentService::class);
        self::assertInstanceOf(DocumentService::class, $documents);
        $page = $documents->loadPageByKey('home');
        self::assertInstanceOf(BuilderPage::class, $page);
        $documents->flushPage($page);

        self::assertSame(['home'], $default->getConnection()->fetchFirstColumn($this->sql($default, 'SELECT {pageKey} FROM pb_page')), 'same connection and tables');
        self::assertSame('published', $default->getConnection()->fetchOne($this->sql($default, 'SELECT {status} FROM pb_page')));
        self::assertSame(['stored'], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note'), 'the application\'s changes are not flushed');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($pending));
        self::assertFalse($default->contains($page), 'the page belongs to the private manager');
        self::assertTrue($private->contains($page));
        self::assertSame([], $this->probeNotices(), 'no probe in private mode');

        $default->flush();
        self::assertSame(['edited, not flushed', 'pending insert'], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note ORDER BY id'));
    }

    public function testAFailedPrivateWriteClosesOnlyThePrivateManager(): void
    {
        $container = $this->boot(['private_entity_manager' => true]);
        $default   = $this->defaultManager($container);
        $private   = $container->get('nowo_page_builder_kit.entity_manager');
        self::assertInstanceOf(PrivateEntityManager::class, $private);
        $pending = new HostNote('pending');
        $default->persist($pending);

        $private->persist(new BuilderPage()->setPageKey('dup-a')->setUuid('00000000-0000-4000-8000-000000000001'));
        $private->persist(new BuilderPage()->setPageKey('dup-b')->setUuid('00000000-0000-4000-8000-000000000001'));

        try {
            $private->flush();
            self::fail('the duplicate uuid must fail');
        } catch (Throwable) {
        }

        self::assertTrue($default->isOpen(), 'the application\'s manager stays open');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($pending));
        $this->publisher($container)->createPageByKey('after', 'After', 'en');
        self::assertSame(['after'], $default->getConnection()->fetchFirstColumn($this->sql($default, 'SELECT {pageKey} FROM pb_page')), 'a fresh private manager writes on');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($pending));
    }

    /**
     * Worker mode, kernel reset off: no managed page survives a request; a row changed behind Doctrine's back between
     * requests is read fresh; memory stays flat.
     */
    public function testKeptAliveKernelDropsThePrivateManagerAtEveryRequest(): void
    {
        $container = $this->boot(['private_entity_manager' => true]);
        $private   = $container->get('nowo_page_builder_kit.entity_manager');
        self::assertInstanceOf(PrivateEntityManager::class, $private);
        $default = $this->defaultManager($container);
        $note    = new HostNote('host change survives');
        $default->persist($note);
        $kernel = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $kernel);

        self::assertSame(200, $this->request($kernel, '/t/create/tenant-a'));
        self::assertFalse($private->isCreated(), 'dropped at the end of the request');

        $baseline = 0;
        for ($i = 0; $i < 200; ++$i) {
            $default->getConnection()->executeStatement($this->sql($default, 'UPDATE pb_page SET {status} = ? WHERE {pageKey} = ?'), [$i % 2 === 0 ? 'published' : 'draft', 'tenant-a']);
            $request  = Request::create('/t/show/tenant-a');
            $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
            self::assertSame('tenant-a|' . ($i % 2 === 0 ? 'published' : 'draft'), $response->getContent(), 'fresh data, never a previous request\'s identity map');
            $kernel->terminate($request, $response);
            self::assertFalse($private->isCreated());
            if ($i === 50) {
                $baseline = memory_get_usage();
            }
        }

        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $baseline, 'memory stays flat over many requests');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($note), 'the application\'s manager is never cleared');
    }

    public function testABoundaryDropsThePrivateManagerAndNeverBuildsOne(): void
    {
        $container = $this->boot(['private_entity_manager' => true]);
        $private   = $container->get('nowo_page_builder_kit.entity_manager');
        self::assertInstanceOf(PrivateEntityManager::class, $private);
        $subscriber = $container->get(OwnEntityManagerSubscriber::class);
        self::assertInstanceOf(OwnEntityManagerSubscriber::class, $subscriber);

        $this->publisher($container)->createPageByKey('cmd', 'Command', 'en');
        self::assertTrue($private->isCreated());
        $subscriber->clearState();
        self::assertFalse($private->isCreated(), 'a boundary drops it');
        $subscriber->clearState();
        self::assertFalse($private->isCreated(), 'a boundary never builds one');
    }

    public function testDefaultPathReportsTheApplicationsChangesItFlushesOncePerRequest(): void
    {
        $container = $this->boot([]);
        $default   = $this->defaultManager($container);
        self::assertSame($default, $container->get('nowo_page_builder_kit.entity_manager'));
        $publisher = $this->publisher($container);
        $publisher->createPageByKey('probe', 'Probe', 'en');
        $documents = $container->get(DocumentService::class);
        self::assertInstanceOf(DocumentService::class, $documents);
        $page = $documents->loadPageByKey('probe');
        self::assertInstanceOf(BuilderPage::class, $page);

        $default->persist(new HostNote('flushed by the bundle'));
        $documents->flushPage($page);
        $default->persist(new HostNote('again'));
        $documents->flushPage($page);

        self::assertSame([self::NOTICE], $this->probeNotices());
        self::assertSame(['flushed by the bundle', 'again'], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note ORDER BY id'), '1.x behaviour unchanged');

        // The next request is a new unit of work: reported again.
        $kernel = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $kernel);
        self::assertSame(200, $this->request($kernel, '/t/show/probe'));
        $default->persist(new HostNote('next unit'));
        $documents->flushPage($page);
        self::assertSame([self::NOTICE, self::NOTICE], $this->probeNotices());
    }

    /**
     * Audit XD-4: `doctrine.entity_manager` naming the default manager is the default path under another spelling: the
     * build notice is triggered and every bundle flush of that manager (here `flushPage()`) runs the probe.
     */
    public function testEntityManagerNamingTheDefaultManagerGetsTheNoticeAndTheProbe(): void
    {
        $container = $this->boot(['entity_manager' => 'default', 'table_prefix' => 'named_' . md5((string) microtime(true)) . '_'], true);
        $build     = [];
        foreach ($this->deprecations as $message) {
            if (str_contains($message, NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION)) {
                $build[] = $message;
            }
        }
        self::assertCount(1, $build, 'the build notice of an unset option');
        $this->deprecations = [];

        $default = $this->defaultManager($container);
        self::assertSame($default, $container->get('nowo_page_builder_kit.entity_manager'));
        $this->publisher($container)->createPageByKey('named', 'Named', 'en');
        $documents = $container->get(DocumentService::class);
        self::assertInstanceOf(DocumentService::class, $documents);
        $page = $documents->loadPageByKey('named');
        self::assertInstanceOf(BuilderPage::class, $page);

        $default->persist(new HostNote('flushed by flushPage'));
        $documents->flushPage($page);

        self::assertSame([self::NOTICE], $this->probeNotices());
        self::assertSame(['flushed by flushPage'], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note ORDER BY id'), '1.x behaviour unchanged');
    }

    public function testDefaultPathWithoutApplicationChangesReportsNothing(): void
    {
        $container = $this->boot([]);
        $this->publisher($container)->createPageByKey('quiet', 'Quiet', 'en');
        $this->publisher($container)->publishByKey('quiet');

        self::assertSame([], $this->probeNotices());
        $repository = $container->get(BuilderPageRepositoryInterface::class);
        self::assertInstanceOf(BuilderPageRepositoryInterface::class, $repository);
        self::assertSame('quiet', $repository->findOneByPageKey('quiet')?->getPageKey());
    }

    public function testTheBuildNoticeNamesTheOptionWhenItIsUnset(): void
    {
        $this->kernel = new PersistenceTestKernel($this->doctrine(), ['table_prefix' => 'notice_' . md5((string) microtime(true)) . '_']);
        $this->kernel->boot();

        $notices = [];
        foreach ($this->deprecations as $message) {
            if (str_contains($message, NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION)) {
                $notices[] = $message;
            }
        }

        self::assertCount(1, $notices, 'once per container build');
    }

    /** Replaces `{field}` with the column of that BuilderPage field (the host's naming strategy). */
    private function sql(EntityManagerInterface $manager, string $sql): string
    {
        $metadata = $manager->getClassMetadata(BuilderPage::class);

        return (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => $metadata->getColumnName($m[1]), $sql);
    }

    private function request(HttpKernel $kernel, string $uri): int
    {
        $request  = Request::create($uri);
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        $kernel->terminate($request, $response);

        return $response->getStatusCode();
    }

    /**
     * @return list<string>
     */
    private function probeNotices(): array
    {
        $notices = [];
        foreach ($this->deprecations as $message) {
            if (str_contains($message, 'were flushed by NowoPageBuilderKitBundle')) {
                $notices[] = $message;
            }
        }

        return $notices;
    }

    private function publisher(ContainerInterface $container): PagePublisherInterface
    {
        $publisher = $container->get(PagePublisherInterface::class);
        self::assertInstanceOf(PagePublisherInterface::class, $publisher);

        return $publisher;
    }

    private function defaultManager(ContainerInterface $container): EntityManagerInterface
    {
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * @param array<string, mixed> $bundleDoctrine
     * @param bool $keepBuildDeprecations keep the deprecations of the container build (a fresh cache directory only)
     */
    private function boot(array $bundleDoctrine, bool $keepBuildDeprecations = false): ContainerInterface
    {
        $this->kernel = new PersistenceTestKernel($this->doctrine(), $bundleDoctrine);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $default = $this->defaultManager($container);
        $tool    = new SchemaTool($default);
        $tool->dropDatabase();
        $tool->createSchema($default->getMetadataFactory()->getAllMetadata());
        if (!$keepBuildDeprecations) {
            $this->deprecations = [];
        }

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    private function doctrine(): array
    {
        return [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/private.sqlite'],
            'orm'  => ['mappings' => [
                'Host' => [
                    'type'      => 'attribute',
                    'dir'       => dirname(__DIR__, 2) . '/Fixtures/Host',
                    'prefix'    => 'Nowo\\PageBuilderKitBundle\\Tests\\Fixtures\\Host',
                    'is_bundle' => false,
                ],
            ]],
        ];
    }
}
