<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
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
 * 2.0 (REQ-ARCH-001 rule 6): the private entity manager, the only mode with zero configuration, through a real kernel
 * (SQLite). The application's pending changes stay pending; the private manager is dropped at every boundary of a
 * kept-alive kernel (FrankenPHP worker mode with kernel reset off, no services resetter, no
 * NowoTransactionDoctrineBundle) and replaced when a failed write closed it. No notice and no host-flush probe remain.
 */
final class PrivateEntityManagerKernelTest extends TestCase
{
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
        $container = $this->boot([]);
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
        self::assertSame([], $this->probeNotices(), 'no probe in 2.0');

        $default->flush();
        self::assertSame(['edited, not flushed', 'pending insert'], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note ORDER BY id'));
    }

    public function testAFailedPrivateWriteClosesOnlyThePrivateManager(): void
    {
        $container = $this->boot([]);
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
        $container = $this->boot([]);
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
        $container = $this->boot([]);
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

    /**
     * W0-1: `doctrine.entity_manager` naming the default manager was the 1.x default path under another spelling (the
     * bundle flushed it); since 2.0 it is the private manager on that manager, without a notice.
     */
    public function testEntityManagerNamingTheDefaultManagerUsesThePrivateManager(): void
    {
        $container = $this->boot(['entity_manager' => 'default', 'table_prefix' => 'named_' . md5((string) microtime(true)) . '_'], true);
        self::assertSame([], $this->bundleDeprecations(), 'no notice');

        $default = $this->defaultManager($container);
        $private = $container->get('nowo_page_builder_kit.entity_manager');
        self::assertInstanceOf(PrivateEntityManager::class, $private);
        $this->publisher($container)->createPageByKey('named', 'Named', 'en');
        $documents = $container->get(DocumentService::class);
        self::assertInstanceOf(DocumentService::class, $documents);
        $page = $documents->loadPageByKey('named');
        self::assertInstanceOf(BuilderPage::class, $page);

        $pending = new HostNote('not flushed by flushPage');
        $default->persist($pending);
        $documents->flushPage($page);

        self::assertSame([], $this->probeNotices());
        self::assertSame([], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note ORDER BY id'), 'the application\'s change is not flushed');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($pending));
        self::assertTrue($container->has(OwnEntityManagerSubscriber::class), 'the subscriber drops the private manager');
    }

    public function testZeroConfigBuildsWithoutNoticeAndWritesThroughThePrivateManager(): void
    {
        $this->kernel = new PersistenceTestKernel($this->doctrine(), ['table_prefix' => 'notice_' . md5((string) microtime(true)) . '_']);
        $this->kernel->boot();

        self::assertSame([], $this->bundleDeprecations(), 'no notice when the container is built');
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf(PrivateEntityManager::class, $container->get('nowo_page_builder_kit.entity_manager'));
    }

    /**
     * Q4: `private_entity_manager: true` stays valid in 2.x as a deprecated no-op (removed in 3.0).
     */
    public function testTrueIsADeprecatedNoOp(): void
    {
        $container = $this->boot(['private_entity_manager' => true, 'table_prefix' => 'true_' . md5((string) microtime(true)) . '_'], true);

        // The configuration is processed again by Symfony's env-placeholder validation: the same notice, once per pass.
        $notices = $this->bundleDeprecations();
        self::assertNotSame([], $notices);
        foreach ($notices as $notice) {
            self::assertStringContainsString('The "private_entity_manager" option at "nowo_page_builder_kit.doctrine" has no effect since 2.0', $notice);
        }
        self::assertInstanceOf(PrivateEntityManager::class, $container->get('nowo_page_builder_kit.entity_manager'));
    }

    /**
     * @return list<string> the deprecations of this bundle
     */
    private function bundleDeprecations(): array
    {
        $notices = [];
        foreach ($this->deprecations as $message) {
            if (str_contains($message, 'nowo-tech/page-builder-kit-bundle') || str_contains($message, 'NowoPageBuilderKitBundle')) {
                $notices[] = $message;
            }
        }

        return $notices;
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
