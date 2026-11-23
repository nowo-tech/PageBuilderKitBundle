<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Database;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\Host\HostNote;
use Nowo\PageBuilderKitBundle\Tests\Integration\Persistence\HostPendingChangesTestCase;
use Nowo\PageBuilderKitBundle\Tests\Integration\Persistence\PersistenceTestKernel;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function dirname;
use function getenv;
use function is_string;
use function preg_replace_callback;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * 2.0 (REQ-ARCH-001 rule 6): the application's pending changes against SQLite, MySQL 8.4, MariaDB 11.4 and PostgreSQL
 * 16, with zero configuration (the private entity manager is the only mode). They stay pending through every bundle
 * write, also through a write refused by a lock conflict (REQ-RUNTIME-003 rule 6: nothing committed, the application's
 * manager open and untouched, the next write goes through). No host-flush probe remains. The full data-loss scenario
 * is {@see HostPendingChangesTestCase}.
 *
 * Run by `make test-with-db` and the CI "Real databases" job (PBK_MYSQL_URL, PBK_MARIADB_URL, PBK_POSTGRES_URL).
 */
#[Group('database')]
final class PrivateEntityManagerDatabaseTest extends TestCase
{
    private ?PersistenceTestKernel $kernel = null;

    private ?Connection $other = null;

    private string $url = '';

    /** @var list<string> */
    private array $deprecations = [];

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function databases(): iterable
    {
        yield 'SQLite' => [null];
        yield 'MySQL 8.4' => ['PBK_MYSQL_URL'];
        yield 'MariaDB 11.4' => ['PBK_MARIADB_URL'];
        yield 'PostgreSQL 16' => ['PBK_POSTGRES_URL'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function servers(): iterable
    {
        yield 'MySQL 8.4' => ['PBK_MYSQL_URL'];
        yield 'MariaDB 11.4' => ['PBK_MARIADB_URL'];
        yield 'PostgreSQL 16' => ['PBK_POSTGRES_URL'];
    }

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
        if ($this->other instanceof Connection) {
            if ($this->other->isTransactionActive()) {
                $this->other->rollBack();
            }
            $this->other->close();
            $this->other = null;
        }
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    #[DataProvider('databases')]
    public function testPrivateModeLeavesTheApplicationsPendingChangesPending(?string $env): void
    {
        [$container, $default] = $this->boot($env);
        $stored                = new HostNote('stored');
        $default->persist($stored);
        $default->flush();
        $stored->text = 'edited';
        $pending      = new HostNote('pending');
        $default->persist($pending);

        $documents = $this->documents($container);
        $page      = $documents->createPage('home', 'Home', 'en');
        $documents->saveDocument($page, $this->grapes('<p>v1</p>'), []);
        $documents->publish($page);
        $documents->flushPage($page);

        self::assertSame('published', $this->other()->fetchOne($this->sql($default, 'SELECT {status} FROM pb_page WHERE {pageKey} = ?'), ['home']));
        self::assertSame(['stored'], $this->other()->fetchFirstColumn('SELECT text FROM host_note'), 'nothing of the application is written');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($pending));
        self::assertTrue($default->isOpen());
        self::assertSame([], $this->probeNotices());

        $default->flush();
        self::assertSame(['edited', 'pending'], $this->other()->fetchFirstColumn('SELECT text FROM host_note ORDER BY id'));
    }

    /**
     * REQ-RUNTIME-003 rule 6 in private mode: a save that meets a row lock fails transiently (409 + CONFLICT_001 in the
     * HTTP answer, see PageBuilderConcurrencyDatabaseTest) with nothing committed; the application's pending changes
     * and its manager are untouched, and the next save goes through on a fresh private manager.
     */
    #[DataProvider('servers')]
    public function testALockConflictInPrivateModeNeverTouchesTheApplicationsManager(string $env): void
    {
        [$container, $default] = $this->boot($env);
        $documents             = $this->documents($container);
        $documents->saveDocument($documents->createPage('locked', 'Locked', 'en'), $this->grapes('<p>v1</p>'), []);
        $pending = new HostNote('pending through a conflict');
        $default->persist($pending);

        $documentId = $this->other()->fetchOne('SELECT d.id FROM pb_document d JOIN pb_page p ON p.id = d.' . $this->sql($default, '{page}', BuilderDocument::class) . ' WHERE p.' . $this->sql($default, '{pageKey}') . ' = ?', ['locked']);
        $this->other()->beginTransaction();
        $this->other()->fetchOne('SELECT id FROM pb_document WHERE id = ? FOR UPDATE', [$documentId]);

        $page = $documents->loadPageByKey('locked');
        self::assertInstanceOf(BuilderPage::class, $page);

        try {
            $documents->saveDocument($page, $this->grapes('<p>v2</p>'), []);
            self::fail('The save must meet the lock.');
        } catch (TransientStorageFailure) {
        }

        $this->other()->rollBack();
        self::assertStringContainsString('v1', (string) $this->other()->fetchOne($this->sql($default, 'SELECT d.{structure} FROM pb_document d WHERE d.id = ?', BuilderDocument::class), [$documentId]), 'nothing committed');
        self::assertTrue($default->isOpen());
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($pending), 'the application\'s change is still pending');
        self::assertSame(0, (int) $this->other()->fetchOne('SELECT COUNT(*) FROM host_note'));

        $page = $documents->loadPageByKey('locked');
        self::assertInstanceOf(BuilderPage::class, $page);
        $documents->saveDocument($page, $this->grapes('<p>v3</p>'), []);
        self::assertStringContainsString('v3', (string) $this->other()->fetchOne($this->sql($default, 'SELECT d.{structure} FROM pb_document d WHERE d.id = ?', BuilderDocument::class), [$documentId]));
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($pending));
    }

    /**
     * @return array{0: ContainerInterface, 1: EntityManagerInterface}
     */
    private function boot(?string $env): array
    {
        if ($env === null) {
            $this->url = '';
            $dbal      = ['url' => 'sqlite:///%kernel.cache_dir%/private-db.sqlite'];
        } else {
            $url = getenv($env);
            if (!is_string($url) || $url === '') {
                self::markTestSkipped($env . ' is not set (make test-with-db).');
            }
            $this->url = $url;
            $dbal      = ['url' => $url];
        }

        $this->kernel = new PersistenceTestKernel([
            'dbal' => $dbal,
            'orm'  => ['mappings' => [
                'Host' => [
                    'type'      => 'attribute',
                    'dir'       => dirname(__DIR__, 2) . '/Fixtures/Host',
                    'prefix'    => 'Nowo\\PageBuilderKitBundle\\Tests\\Fixtures\\Host',
                    'is_bundle' => false,
                ],
            ]],
        ]);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $default = $registry->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $default);
        $tool = new SchemaTool($default);
        $tool->dropDatabase();
        $tool->createSchema($default->getMetadataFactory()->getAllMetadata());
        if ($this->url !== '') {
            DatabaseUrl::limitLockWait($default->getConnection(), 1);
        }
        self::assertInstanceOf(PrivateEntityManager::class, $container->get('nowo_page_builder_kit.entity_manager'), 'zero configuration');
        $this->deprecations = [];

        return [$container, $default];
    }

    private function documents(ContainerInterface $container): DocumentService
    {
        $documents = $container->get(DocumentService::class);
        self::assertInstanceOf(DocumentService::class, $documents);

        return $documents;
    }

    /** A second session (the test's reads), or the kernel's own connection on SQLite (a single file, no locks). */
    private function other(): Connection
    {
        if ($this->other instanceof Connection) {
            return $this->other;
        }

        if ($this->url === '') {
            self::assertNotNull($this->kernel);
            $registry = $this->kernel->getContainer()->get('doctrine');
            self::assertInstanceOf(ManagerRegistry::class, $registry);
            $connection = $registry->getConnection();
            self::assertInstanceOf(Connection::class, $connection);

            return $connection;
        }

        $this->other = DatabaseUrl::connect($this->url);
        DatabaseUrl::limitLockWait($this->other, 5);

        return $this->other;
    }

    /**
     * Replaces `{field}` with the column of that field of $class (the host's naming strategy).
     *
     * @param class-string $class
     */
    private function sql(EntityManagerInterface $manager, string $sql, string $class = BuilderPage::class): string
    {
        $metadata = $manager->getClassMetadata($class);

        return (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => $metadata->hasAssociation($m[1]) ? $metadata->getSingleAssociationJoinColumnName($m[1]) : $metadata->getColumnName($m[1]), $sql);
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

    /**
     * @return array<string, mixed>
     */
    private function grapes(string $html): array
    {
        return ['version' => 2, 'engine' => 'grapesjs', 'html' => $html, 'css' => '', 'grapes' => [], 'localeContent' => [], 'fields' => [], 'fieldValues' => []];
    }
}
