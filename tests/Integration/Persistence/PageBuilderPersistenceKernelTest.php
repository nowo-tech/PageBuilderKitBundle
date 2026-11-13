<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Event\PageCreatedEvent;
use Nowo\PageBuilderKitBundle\Event\PageDocumentSavedEvent;
use Nowo\PageBuilderKitBundle\Event\PageMetadataSavedEvent;
use Nowo\PageBuilderKitBundle\Event\PagePublishedEvent;
use Nowo\PageBuilderKitBundle\Event\PageRevisionRestoredEvent;
use Nowo\PageBuilderKitBundle\Event\PageTemplateChangedEvent;
use Nowo\PageBuilderKitBundle\Event\PageUnpublishedEvent;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderPendingEventsInterface;
use Nowo\PageBuilderKitBundle\Persistence\UnitOfWorkBoundaries;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsService;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PagePublisherInterface;
use Nowo\PageBuilderKitBundle\Service\PageRevisionService;
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\Host\HostNote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;
use WeakMap;

use function array_map;
use function dirname;
use function gc_collect_cycles;
use function intdiv;
use function memory_get_usage;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Rule 6/7 through a real kernel (1.14): which entity manager the bundle uses and flushes, the dedicated
 * `doctrine.entity_manager`, the host-controlled transaction mode and a kept-alive kernel (FrankenPHP worker
 * without kernel reset, no services resetter).
 */
final class PageBuilderPersistenceKernelTest extends TestCase
{
    private ?PersistenceTestKernel $kernel = null;

    /** @var list<object> */
    private array $events = [];

    /** Requests of the two-tenant worker test (a read and a DBAL write each; SQLite file). */
    private const int WORKER_ITERATIONS = 1000;

    private const int WORKER_MEMORY_MARGIN = 4 * 1024 * 1024;

    private string $pageKeyColumn = 'page_key';

    /** @var WeakMap<Command, ArrayInput> */
    private WeakMap $commandInputs;

    protected function setUp(): void
    {
        $this->commandInputs = new WeakMap();
    }

    /** @var list<bool> transaction state of the bundle connection when each event was dispatched */
    private array $inTransaction = [];

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testStandardHostUsesTheDefaultManagerAndDispatchesIdEventsAfterCommit(): void
    {
        $container = $this->boot($this->singleManager(), []);
        $default   = $this->manager($container, 'default');

        self::assertSame($default, $container->get('nowo_page_builder_kit.entity_manager'));
        self::assertFalse($container->has(OwnEntityManagerSubscriber::class), 'the host manager is never reset by the bundle');

        $publisher = $container->get(PagePublisherInterface::class);
        self::assertInstanceOf(PagePublisherInterface::class, $publisher);
        $uuid = $publisher->createPageByKey('home', 'Home', 'en');
        $publisher->publishByKey('home');

        self::assertSame(['home'], $this->pageKeys($default));
        self::assertCount(2, $this->events);
        self::assertInstanceOf(PageCreatedEvent::class, $this->events[0]);
        self::assertSame(['home', $uuid], [$this->events[0]->pageKey, $this->events[0]->pageUuid]);
        self::assertInstanceOf(PagePublishedEvent::class, $this->events[1]);
        self::assertSame($uuid, $this->events[1]->pageUuid);
        self::assertSame([false, false], $this->inTransaction, 'events are dispatched after the commit');
    }

    public function testDedicatedManagerWithAutoMappingOnDefaultKeepsTheHostUnitOfWorkUntouched(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder']);
        $registry  = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $default = $this->manager($container, 'default');
        $bundle  = $this->manager($container, 'page_builder');

        self::assertTrue($default->getMetadataFactory()->isTransient(BuilderPage::class), 'not mapped on the auto-mapped default manager');
        self::assertSame($bundle, $registry->getManagerForClass(BuilderPage::class));
        self::assertSame($bundle, $container->get('nowo_page_builder_kit.entity_manager'));
        self::assertSame('default', $registry->getDefaultManagerName());

        $note = new HostNote('pending host change');
        $default->persist($note);

        $publisher = $container->get(PagePublisherInterface::class);
        self::assertInstanceOf(PagePublisherInterface::class, $publisher);
        $publisher->createPageByKey('about', 'About', 'en');
        $publisher->publishByKey('about');

        self::assertSame(['about'], $this->pageKeys($bundle));
        self::assertSame([], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note'), 'the host change is not flushed by the bundle');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($note));
        self::assertNotContains('pb_page', $default->getConnection()->createSchemaManager()->listTableNames());

        $repository = $container->get(BuilderPageRepositoryInterface::class);
        self::assertInstanceOf(BuilderPageRepositoryInterface::class, $repository);
        self::assertSame('about', $repository->findOneByPageKey('about')?->getPageKey());
    }

    /**
     * DoctrineBundle 3.4 regression: `auto_discover_entities` (on by default for a manager without `mappings` and
     * `auto_mapping`) gives the host manager's chain a default attribute driver, which claims every `#[ORM\Entity]`
     * class, so `getManagerForClass()` answered the host manager and the repositories queried its database. The
     * explicit catch-all default driver reproduces it on every DoctrineBundle version.
     */
    #[DataProvider('hostManagersWithADefaultDriver')]
    public function testDedicatedManagerWinsOverADefaultDriverOfTheHostManager(bool $explicitDefaultDriver): void
    {
        $doctrine                                      = $this->twoManagers(autoMapping: false);
        $doctrine['orm']['entity_managers']['default'] = ['connection' => 'default'];
        $resolvedBeforeInit                            = [];
        $container                                     = $this->boot($doctrine, ['entity_manager' => 'page_builder'], hostDefaultDriver: $explicitDefaultDriver, beforeSchema: static function (ManagerRegistry $registry) use (&$resolvedBeforeInit): void {
            // No manager is initialized yet: DoctrineBundle 3.4 asks the metadata drivers.
            $resolvedBeforeInit = [$registry->getManagerForClass(BuilderPage::class), $registry->getManagerForClass(BuilderPageRevision::class)];
        });
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $default = $this->manager($container, 'default');
        $bundle  = $this->manager($container, 'page_builder');

        self::assertSame([$bundle, $bundle], $resolvedBeforeInit);
        self::assertTrue($default->getMetadataFactory()->isTransient(BuilderPage::class), 'not mapped on the host manager');
        self::assertSame($bundle, $registry->getManagerForClass(BuilderPage::class));
        self::assertSame($bundle, $registry->getManagerForClass(BuilderPageRevision::class));

        $publisher = $container->get(PagePublisherInterface::class);
        self::assertInstanceOf(PagePublisherInterface::class, $publisher);
        $publisher->createPageByKey('about', 'About', 'en');

        self::assertSame(['about'], $this->pageKeys($bundle));
        self::assertNotContains('pb_page', $default->getConnection()->createSchemaManager()->listTableNames());
        $bundle->clear();
        $repository = $container->get(BuilderPageRepositoryInterface::class);
        self::assertInstanceOf(BuilderPageRepositoryInterface::class, $repository);
        self::assertSame('about', $repository->findOneByPageKey('about')?->getPageKey(), 'the repository reads the bundle database');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function hostManagersWithADefaultDriver(): iterable
    {
        yield 'host manager without mappings (DoctrineBundle 3.4 auto-discovery)' => [false];
        yield 'host manager with a catch-all default driver' => [true];
    }

    public function testManagersWithoutDefaultEntityManagerKeepTheHostDefault(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: false, defaultName: false), ['entity_manager' => 'page_builder']);
        $registry  = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        self::assertSame('default', $registry->getDefaultManagerName());
        self::assertTrue($this->manager($container, 'default')->getMetadataFactory()->isTransient(BuilderPage::class));
        self::assertSame($this->manager($container, 'page_builder'), $registry->getManagerForClass(BuilderPage::class));
    }

    public function testEveryWriteRecordsAnIdEventOnTheDedicatedManager(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder'], revisions: true);
        $default   = $this->manager($container, 'default');
        $note      = new HostNote('pending');
        $default->persist($note);

        $documents = $container->get(DocumentService::class);
        $templates = $container->get(PageTemplateService::class);
        $fields    = $container->get(ContentFieldsService::class);
        $revisions = $container->get(PageRevisionService::class);
        self::assertInstanceOf(DocumentService::class, $documents);
        self::assertInstanceOf(PageTemplateService::class, $templates);
        self::assertInstanceOf(ContentFieldsService::class, $fields);
        self::assertInstanceOf(PageRevisionService::class, $revisions);

        $page = $documents->createPage('landing', 'Landing', 'en');
        $documents->saveDocumentByKey('landing', ['version' => 1, 'sections' => []]);
        $fields->saveSchema($page, [['key' => 'headline', 'type' => 'string', 'label' => 'Headline']]);
        $revision = $revisions->create($page, 'manual');
        self::assertNotNull($revision?->getId());
        $revisions->restore($page, $revision->getId());
        $documents->flushPage($page);
        $templates->saveFromPage($page, 'tpl', 'Template');
        $templates->import($templates->export('tpl'));
        $templates->delete('tpl');
        $documents->publishByKey('landing');
        $documents->unpublishByKey('landing');

        $summary = [];
        foreach ($this->events as $event) {
            $summary[] = match (true) {
                $event instanceof PageDocumentSavedEvent    => 'saved:' . $event->change . ':' . $event->pageKey,
                $event instanceof PageTemplateChangedEvent  => 'template:' . $event->action . ':' . $event->templateKey,
                $event instanceof PageRevisionRestoredEvent => 'restored:' . $event->revisionId . ':' . $event->pageUuid,
                $event instanceof PageCreatedEvent          => 'created:' . $event->pageKey,
                $event instanceof PageMetadataSavedEvent    => 'metadata:' . $event->pageKey,
                $event instanceof PagePublishedEvent        => 'published:' . $event->pageKey,
                $event instanceof PageUnpublishedEvent      => 'unpublished:' . $event->pageKey,
                default                                     => $event::class,
            };
        }

        self::assertSame([
            'created:landing',
            'saved:document:landing',
            'saved:content_fields:landing',
            'saved:document:landing',
            'restored:' . $revision->getId() . ':' . $page->getUuid(),
            'metadata:landing',
            'template:saved:tpl',
            'template:imported:tpl',
            'template:deleted:tpl',
            'published:landing',
            'unpublished:landing',
        ], $summary);
        self::assertNotContains(true, $this->inTransaction);
        self::assertSame([], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note'));
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($note));
    }

    public function testUnknownEntityManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_page_builder_kit.doctrine.entity_manager is "missing"');

        $this->boot($this->singleManager(), ['entity_manager' => 'missing']);
    }

    public function testHostTransactionModeNeverFlushesAndDispatchesAfterTheHostCommit(): void
    {
        $container = $this->boot($this->singleManager(), ['transaction' => 'host']);
        $default   = $this->manager($container, 'default');
        $pending   = $container->get(PageBuilderPendingEventsInterface::class);
        self::assertInstanceOf(PageBuilderPendingEventsInterface::class, $pending);
        $publisher = $container->get(PagePublisherInterface::class);
        self::assertInstanceOf(PagePublisherInterface::class, $publisher);

        $publisher->createPageByKey('draft', 'Draft', 'en');
        self::assertSame([], $this->pageKeys($default), 'the bundle does not flush');
        self::assertSame([], $this->events);
        self::assertSame(1, $pending->countPending());

        // Host use case: its own transaction (Messenger doctrine_transaction middleware does the same).
        $default->wrapInTransaction(static function (EntityManagerInterface $em): void {
            $em->flush();
        });
        self::assertSame(['draft'], $this->pageKeys($default));
        self::assertSame([], $this->events, 'postFlush inside the host transaction does not dispatch');
        self::assertSame(1, $pending->dispatchPending());
        self::assertCount(1, $this->events);
        self::assertInstanceOf(PageCreatedEvent::class, $this->events[0]);

        // Host flush outside a transaction: dispatched on postFlush, after the commit.
        $publisher->publishByKey('draft');
        self::assertCount(1, $this->events);
        $default->flush();
        self::assertCount(2, $this->events);
        self::assertInstanceOf(PagePublishedEvent::class, $this->events[1]);
        self::assertSame([false, false], $this->inTransaction);
        self::assertSame(0, $pending->countPending());
    }

    public function testBundleModeInsideAHostTransactionThatRollsBackDispatchesNothing(): void
    {
        $container = $this->boot($this->singleManager(), []);
        $default   = $this->manager($container, 'default');
        $publisher = $this->publisher($container);

        $default->beginTransaction();
        $publisher->createPageByKey('rolledback', 'Rolled back', 'en');
        self::assertSame([], $this->events, 'not inside the host transaction');
        $default->rollback();
        $default->clear();

        $this->terminate($container);
        $publisher->createPageByKey('next', 'Next', 'en');
        $this->terminate($container);

        self::assertSame(['next'], $this->pageKeys($default));
        self::assertSame(['next'], $this->eventKeys(), 'LG-PBK-M1: no event for the rolled-back page');
        self::assertSame([false], $this->inTransaction);
    }

    public function testBundleModeSavepointRollbackDropsOnlyItsEvents(): void
    {
        $container = $this->boot($this->singleManager(), []);
        $default   = $this->manager($container, 'default');
        $publisher = $this->publisher($container);
        $pending   = $this->pending($container);

        $default->beginTransaction();
        $publisher->createPageByKey('kept', 'Kept', 'en');
        $default->beginTransaction(); // savepoint
        $publisher->createPageByKey('dropped', 'Dropped', 'en');
        $default->rollback(); // rollback to the savepoint
        self::assertSame(1, $pending->countPending());
        $default->commit();
        self::assertSame([], $this->events, 'dispatched at the next safe point, not inside the DBAL commit');
        self::assertSame(1, $pending->dispatchPending());

        self::assertSame(['kept'], $this->eventKeys());
        self::assertSame(['kept'], $this->pageKeys($default));
        self::assertSame([false], $this->inTransaction);
    }

    public function testHostModeFlushThenConnectionRollbackDispatchesNothing(): void
    {
        $container  = $this->boot($this->singleManager(), ['transaction' => 'host']);
        $default    = $this->manager($container, 'default');
        $publisher  = $this->publisher($container);
        $pending    = $this->pending($container);
        $connection = $default->getConnection();

        $publisher->createPageByKey('conn-rb', 'Rolled back', 'en');
        $connection->beginTransaction();
        $default->flush();
        $connection->rollBack();
        $default->clear();
        self::assertSame(0, $pending->countPending());

        $this->terminate($container);
        $publisher->createPageByKey('next', 'Next', 'en');
        $default->flush();
        $this->terminate($container);

        self::assertSame(['next'], $this->eventKeys(), 'LG-PBK-M1: no event for the rolled-back page');
        self::assertSame(['next'], $this->pageKeys($default));
    }

    public function testCommittedHostWriteKeepsItsEventWhenTheRequestFailsLater(): void
    {
        $container = $this->boot($this->singleManager(), ['transaction' => 'host']);
        $kernel    = $this->httpKernel($container);

        $request  = Request::create('/t/commit-then-fail/committed');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(500, $response->getStatusCode());
        $kernel->terminate($request, $response);

        self::assertSame(['committed'], $this->pageKeys($this->manager($container, 'default')));
        self::assertSame(['committed'], $this->eventKeys(), 'LG-PBK-L1');
        self::assertSame(0, $this->pending($container)->countPending());
    }

    public function testHostTransactionEventsAreDiscardedWhenTheHostRequestFails(): void
    {
        $container = $this->boot($this->singleManager(), ['transaction' => 'host']);
        $pending   = $container->get(PageBuilderPendingEventsInterface::class);
        self::assertInstanceOf(PageBuilderPendingEventsInterface::class, $pending);
        $kernel = $this->httpKernel($container);

        // The host never flushes: the request ends, the unwritten events must not leak into the next request.
        $request  = Request::create('/t/create/never');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $pending->countPending());

        $kernel->terminate($request, $response);
        self::assertSame(0, $pending->countPending());
        self::assertSame([], $this->events);
        self::assertSame([], $this->pageKeys($this->manager($container, 'default')));
    }

    public function testKeptAliveKernelRecoversTheOwnManagerAndKeepsMemoryFlat(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder']);
        $kernel    = $this->httpKernel($container);
        $default   = $this->manager($container, 'default');
        $bundle    = $this->manager($container, 'page_builder');
        $note      = new HostNote('host change survives');
        $default->persist($note);

        $request  = Request::create('/t/fail');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($bundle->isOpen(), 'the failed write closed the bundle manager');
        $kernel->terminate($request, $response);

        $request  = Request::create('/t/create/after-failure');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $kernel->terminate($request, $response);
        self::assertTrue($bundle->isOpen());
        self::assertSame(['after-failure'], $this->pageKeys($bundle));
        self::assertSame(0, $bundle->getUnitOfWork()->size(), 'no managed page survives the request');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($note), 'the host manager is never reset nor cleared');

        $baseline = 0;
        for ($i = 0; $i < 300; ++$i) {
            $request  = Request::create('/t/create/page-' . $i);
            $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
            self::assertSame(200, $response->getStatusCode());
            $kernel->terminate($request, $response);
            self::assertSame(0, $bundle->getUnitOfWork()->size());
            if ($i === 50) {
                $baseline = memory_get_usage();
            }
        }

        self::assertCount(301, $this->events);
        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $baseline, 'memory stays flat over many requests');
    }

    /**
     * REQ-RUNTIME-002 (1.15.1): two tenants alternate over N requests in ONE kept-alive kernel, no services resetter.
     * Each tenant's row is changed behind Doctrine's back before its request: a manager that kept the page managed
     * across requests would serve the stale status (or the other tenant's page) from its identity map.
     */
    public function testKeptAliveKernelAlternatingTenantsSeeOnlyFreshOwnData(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder']);
        $kernel    = $this->httpKernel($container);
        $bundle    = $this->manager($container, 'page_builder');
        $publisher = $this->publisher($container);
        $publisher->createPageByKey('tenant-a', 'Tenant A', 'en');
        $publisher->createPageByKey('tenant-b', 'Tenant B', 'en');
        $this->terminate($container);

        $connection   = $bundle->getConnection();
        $metadata     = $bundle->getClassMetadata(BuilderPage::class);
        $statusColumn = $metadata->getColumnName('status');
        $baseline     = 0;
        for ($i = 0; $i < self::WORKER_ITERATIONS; ++$i) {
            $tenant = $i % 2 === 0 ? 'tenant-a' : 'tenant-b';
            $status = intdiv($i, 2) % 2 === 0 ? PageStatus::Published : PageStatus::Draft;
            $connection->update($metadata->getTableName(), [$statusColumn => $status->value], [$this->pageKeyColumn => $tenant]);

            $request  = Request::create('/t/show/' . $tenant);
            $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
            self::assertSame($tenant . '|' . $status->value, $response->getContent(), 'request ' . $i . ' sees only its own, fresh page');
            $kernel->terminate($request, $response);
            self::assertSame(0, $bundle->getUnitOfWork()->size(), 'no managed page survives request ' . $i);
            if ($i === 50) {
                $baseline = $this->collectedMemory();
            }
        }

        self::assertLessThan(self::WORKER_MEMORY_MARGIN, $this->collectedMemory() - $baseline, 'memory stays flat over many requests');
    }

    /**
     * REQ-RUNTIME-002 rule 4 (1.15.1), through the real dispatcher: with `transaction: host` on a dedicated manager, a
     * nested command, a command run from a message handler and a `kernel.terminate` inside a command never clear the
     * host's pending bundle writes nor discard their write events; only the outermost boundary does.
     */
    public function testNestedBoundariesNeverDiscardTheHostUnitOfWork(): void
    {
        $container  = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder', 'transaction' => 'host']);
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $bundle    = $this->manager($container, 'page_builder');
        $publisher = $this->publisher($container);

        $outer       = new Command('app:outer');
        $nested      = new Command('app:nested');
        $fromHandler = new Command('app:from-handler');

        // Outer command → nested command → kernel.terminate inside the command.
        $dispatcher->dispatch($this->commandEvent($outer), ConsoleEvents::COMMAND);
        $publisher->createPageByKey('in-command', 'In command', 'en');
        $dispatcher->dispatch($this->commandEvent($nested), ConsoleEvents::COMMAND);
        $dispatcher->dispatch($this->terminateEvent($nested), ConsoleEvents::TERMINATE);
        $this->terminate($container);
        self::assertNotSame([], $bundle->getUnitOfWork()->getScheduledEntityInsertions(), 'the nested boundaries keep the pending page');
        $bundle->flush();
        $dispatcher->dispatch($this->terminateEvent($outer), ConsoleEvents::TERMINATE);
        self::assertSame(0, $bundle->getUnitOfWork()->size(), 'the outermost command clears');

        // Message → command run from the handler.
        $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
        $publisher->createPageByKey('in-message', 'In message', 'en');
        $dispatcher->dispatch($this->commandEvent($fromHandler), ConsoleEvents::COMMAND);
        $dispatcher->dispatch($this->terminateEvent($fromHandler), ConsoleEvents::TERMINATE);
        self::assertNotSame([], $bundle->getUnitOfWork()->getScheduledEntityInsertions(), 'a command run from the handler keeps the pending page');
        $bundle->flush();
        $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED);
        self::assertSame(0, $bundle->getUnitOfWork()->size());

        self::assertSame(['in-command', 'in-message'], $this->pageKeys($bundle));
        self::assertSame(['in-command', 'in-message'], $this->eventKeys(), 'the nested boundaries keep the outer unit\'s write events');
        self::assertSame([false, false], $this->inTransaction);
        self::assertSame(0, $this->pending($container)->countPending());
    }

    /**
     * NL2-1 (1.15.1), through a REAL in-process console Application: a throwing `console.terminate` listener and a
     * throwing `console.error` listener (Symfony then never dispatches the terminate) do not leave the process
     * "inside a command": the next HTTP requests still clear the bundle's own manager.
     */
    public function testThrowingConsoleListenersNeverStopTheOwnManagerClear(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder']);
        $bundle    = $this->manager($container, 'page_builder');
        $this->publisher($container)->createPageByKey('tenant-a', 'Tenant A', 'en');
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $throw = static function (): never {
            throw new RuntimeException('listener failed');
        };

        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:ok', static fn (): int => 0));
        $application->addCommand(new CallbackCommand('app:fail', static function (): never {
            throw new RuntimeException('command failed');
        }));

        foreach ([[ConsoleEvents::TERMINATE, 'app:ok'], [ConsoleEvents::ERROR, 'app:fail']] as [$eventName, $commandName]) {
            $dispatcher->addListener($eventName, $throw);
            self::assertNotSame(0, $application->run($this->input($commandName), new NullOutput()));
            $dispatcher->removeListener($eventName, $throw);

            $kernel   = $this->httpKernel($container);
            $request  = Request::create('/t/show/tenant-a');
            $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
            self::assertSame('tenant-a|draft', $response->getContent());
            $kernel->terminate($request, $response);
            self::assertSame(0, $bundle->getUnitOfWork()->size(), 'after a throwing ' . $eventName . ' listener the request still clears');
        }
    }

    /**
     * NL2-2 (1.15.1): an unknown command run in-process from a command (`console.error` without a command) never
     * discards the outer command's unflushed write events.
     */
    public function testUnknownNestedCommandKeepsTheOuterWriteEvents(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder', 'transaction' => 'host']);
        $bundle    = $this->manager($container, 'page_builder');
        $publisher = $this->publisher($container);

        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:outer', function () use ($publisher, $bundle): int {
            $publisher->createPageByKey('outer', 'Outer', 'en');
            $nested = $this->application();
            self::assertNotSame(0, $nested->run($this->input('zzz:no-such-command'), new NullOutput()));
            $bundle->flush();

            return 0;
        }));

        self::assertSame(0, $application->run($this->input('app:outer'), new NullOutput()));
        self::assertSame(['outer'], $this->pageKeys($bundle));
        self::assertSame(['outer'], $this->eventKeys(), 'the unknown nested command does not drop the outer event');
    }

    /**
     * NL2-v N1: a command that runs itself in-process, whose inner run's error listener throws, ends with its outer run.
     */
    public function testSelfRecursiveCommandWithAThrowingErrorListenerStillEnds(): void
    {
        [$container, $bundle, $dispatcher] = $this->bootWithTenantPage();
        $application                       = $this->application();
        $runs                              = 0;
        $application->addCommand(new CallbackCommand('app:recursive', function () use ($application, &$runs, $dispatcher): int {
            if (++$runs === 1) {
                $throw = self::throwingListener();
                $dispatcher->addListener(ConsoleEvents::ERROR, $throw);
                $application->run($this->input('app:recursive'), new NullOutput());
                $dispatcher->removeListener(ConsoleEvents::ERROR, $throw);

                return 0;
            }

            throw new RuntimeException('inner run failed');
        }));

        self::assertSame(0, $application->run($this->input('app:recursive'), new NullOutput()));
        $this->assertRequestClears($container, $bundle, 'self-recursive command + throwing error listener');
    }

    /**
     * NL2-v N1/N2: listeners that throw at the extreme priorities, and a nested command whose error listener throws.
     */
    public function testThrowingListenersAtExtremePrioritiesAndNestedFailuresStillEnd(): void
    {
        [$container, $bundle, $dispatcher] = $this->bootWithTenantPage();
        $application                       = $this->application();
        $application->addCommand(new CallbackCommand('app:ok', static fn (): int => 0));
        $application->addCommand(new CallbackCommand('app:fail', static function (): never {
            throw new RuntimeException('command failed');
        }));
        $application->addCommand(new CallbackCommand('app:outer', function () use ($application, $dispatcher): int {
            $throw = self::throwingListener();
            $dispatcher->addListener(ConsoleEvents::ERROR, $throw);
            $application->run($this->input('app:fail'), new NullOutput());
            $dispatcher->removeListener(ConsoleEvents::ERROR, $throw);

            return 0;
        }));

        foreach ([
            [ConsoleEvents::ERROR, PHP_INT_MIN, 'app:fail'],
            [ConsoleEvents::TERMINATE, PHP_INT_MAX, 'app:ok'],
            [null, 0, 'app:outer'],
        ] as [$eventName, $priority, $command]) {
            $throw = self::throwingListener();
            if ($eventName !== null) {
                $dispatcher->addListener($eventName, $throw, $priority);
            }
            $application->run($this->input($command), new NullOutput());
            if ($eventName !== null) {
                $dispatcher->removeListener($eventName, $throw);
            }
            $this->assertRequestClears($container, $bundle, $command . ' with a throwing ' . ($eventName ?? 'nested error') . ' listener');
        }
    }

    /**
     * NL2-v N3: a message or a request whose closing event never came inside an in-process command (a throwing
     * WorkerMessageReceived listener, a throwing kernel.exception listener) does not outlive the command.
     */
    public function testMessageOrRequestLeftOpenInsideACommandDoesNotOutliveIt(): void
    {
        [$container, $bundle, $dispatcher] = $this->bootWithTenantPage();
        $kernel                            = $this->httpKernel($container);
        $application                       = $this->application();
        $application->addCommand(new CallbackCommand('app:consume', static function () use ($dispatcher): int {
            $throw = self::throwingListener();
            $dispatcher->addListener(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED, $throw);
            try {
                $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
            } finally {
                $dispatcher->removeListener(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED, $throw);
            }

            return 0;
        }));
        $application->addCommand(new CallbackCommand('app:crawl', static function () use ($dispatcher, $kernel): int {
            $throw = self::throwingListener();
            $dispatcher->addListener(KernelEvents::EXCEPTION, $throw, 4096);
            try {
                $kernel->handle(Request::create('/no-such-route'), HttpKernelInterface::MAIN_REQUEST, true);
            } finally {
                $dispatcher->removeListener(KernelEvents::EXCEPTION, $throw);
            }

            return 0;
        }));

        foreach (['app:consume', 'app:crawl'] as $command) {
            self::assertNotSame(0, $application->run($this->input($command), new NullOutput()));
            $this->assertRequestClears($container, $bundle, $command . ': nothing left in flight');
            $next  = new Command('app:next');
            $start = new ConsoleCommandEvent($next, new ArrayInput([]), new NullOutput());
            $dispatcher->dispatch($start, ConsoleEvents::COMMAND);
            $boundaries = $container->get(UnitOfWorkBoundaries::class);
            self::assertInstanceOf(UnitOfWorkBoundaries::class, $boundaries);
            self::assertTrue($boundaries->isOutermost($start), $command . ': the next command is the outermost unit');
            $dispatcher->dispatch(new ConsoleTerminateEvent($next, $start->getInput(), new NullOutput(), 0), ConsoleEvents::TERMINATE);
        }
    }

    /**
     * NL2-v R1: a worker running in-process outside a command; its handler runs a command whose error listener throws
     * (the frame stays marked, no terminate), the message fails, then the worker stops (or not): the next main request
     * is the outermost unit again and clears the bundle's own manager.
     */
    public function testFailedCommandInsideAFailedMessageDoesNotLeaveTheMessageInFlight(): void
    {
        [$container, $bundle, $dispatcher] = $this->bootWithTenantPage();
        $application                       = $this->application();
        $application->addCommand(new CallbackCommand('app:fail', static function (): never {
            throw new RuntimeException('command failed');
        }));

        foreach ([true, false] as $workerStops) {
            $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
            $throw = self::throwingListener();
            $dispatcher->addListener(ConsoleEvents::ERROR, $throw);
            self::assertNotSame(0, $application->run($this->input('app:fail'), new NullOutput()));
            $dispatcher->removeListener(ConsoleEvents::ERROR, $throw);
            $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_FAILED);
            if ($workerStops) {
                $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_STOPPED);
            }

            $this->assertRequestClears($container, $bundle, 'failed command in a failed message, worker stops: ' . ($workerStops ? 'yes' : 'no'));
        }
    }

    /**
     * An error listener that runs another command in-process: the state stays sane afterwards.
     */
    public function testErrorListenerRunningACommandLeavesASaneState(): void
    {
        [$container, $bundle, $dispatcher] = $this->bootWithTenantPage();
        $application                       = $this->application();
        $application->addCommand(new CallbackCommand('app:notify', static fn (): int => 0));
        $application->addCommand(new CallbackCommand('app:fail', static function (): never {
            throw new RuntimeException('command failed');
        }));
        $runs     = 0;
        $listener = function () use ($application, &$runs): void {
            if ($runs++ === 0) {
                $application->run($this->input('app:notify'), new NullOutput());
            }
        };
        $dispatcher->addListener(ConsoleEvents::ERROR, $listener);
        self::assertNotSame(0, $application->run($this->input('app:fail'), new NullOutput()));
        $dispatcher->removeListener(ConsoleEvents::ERROR, $listener);

        $this->assertRequestClears($container, $bundle, 'after an error listener ran a command');
    }

    /**
     * Audit NX CR2, through the `http_kernel` service: a main request handled in-process while another main request
     * runs (here from a `kernel.response` listener) does not clear the outer request's own manager.
     */
    public function testNestedMainRequestKeepsTheOuterRequestUnit(): void
    {
        [$container, $bundle, $dispatcher] = $this->bootWithTenantPage();
        $kernel                            = $this->httpKernel($container);
        $kept                              = null;
        $nested                            = static function (ResponseEvent $event) use ($bundle, $kernel, &$kept, &$nested, $dispatcher): void {
            $dispatcher->removeListener(KernelEvents::RESPONSE, $nested);
            $page = $bundle->getRepository(BuilderPage::class)->findOneBy(['pageKey' => 'tenant-a']);
            self::assertInstanceOf(BuilderPage::class, $page);
            $kernel->handle(Request::create('/t/show/tenant-a'), HttpKernelInterface::MAIN_REQUEST, true);
            $kept = $bundle->contains($page);
        };
        $dispatcher->addListener(KernelEvents::RESPONSE, $nested);

        $request  = Request::create('/t/show/tenant-a');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertTrue($kept, 'the nested main request did not clear the outer request');
        $kernel->terminate($request, $response);
        self::assertSame(0, $bundle->getUnitOfWork()->size());
        $this->assertRequestClears($container, $bundle, 'after a nested main request');
    }

    /**
     * Audit NX CR1/CR3, through a real Messenger worker on the kernel's dispatcher: a worker run inside a handler and
     * a main request handled by that handler do not clear the outer message's unit; a skipped message ends.
     */
    public function testWorkerInsideAHandlerKeepsTheOuterMessageUnit(): void
    {
        [$container, $bundle, $dispatcher] = $this->bootWithTenantPage();
        $kernel                            = $this->httpKernel($container);
        $kept                              = null;
        $this->runWorker($dispatcher, function () use ($bundle, $kernel, $dispatcher, &$kept): void {
            $page = $bundle->getRepository(BuilderPage::class)->findOneBy(['pageKey' => 'tenant-a']);
            self::assertInstanceOf(BuilderPage::class, $page);
            $this->runWorker($dispatcher, static function (): void {
            });
            $kernel->handle(Request::create('/t/show/tenant-a'), HttpKernelInterface::MAIN_REQUEST, true);
            $kept = $bundle->contains($page);
        });
        self::assertTrue($kept, 'the inner worker and the in-process request kept the outer message unit');
        self::assertSame(0, $bundle->getUnitOfWork()->size(), 'the outer message clears when handled');

        $skip = static function (WorkerMessageReceivedEvent $event): void {
            $event->shouldHandle(false);
        };
        $dispatcher->addListener(WorkerMessageReceivedEvent::class, $skip);
        $this->runWorker($dispatcher, static function (): void {
        }, stopAfterFirst: true);
        $dispatcher->removeListener(WorkerMessageReceivedEvent::class, $skip);
        $this->assertRequestClears($container, $bundle, 'after a skipped message');
    }

    private function runWorker(EventDispatcherInterface $dispatcher, Closure $handler, bool $stopAfterFirst = false): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new stdClass()));
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use ($handler): Envelope {
            $handler();

            return $message instanceof Envelope ? $message : new Envelope($message);
        });
        $worker = new Worker(['memory' => $transport], $bus, $dispatcher);
        $stop   = static function (WorkerRunningEvent $event) use ($worker, $stopAfterFirst): void {
            if ($event->getWorker() === $worker && ($stopAfterFirst || $event->isWorkerIdle())) {
                $worker->stop();
            }
        };
        $dispatcher->addListener(WorkerRunningEvent::class, $stop, -1);
        try {
            $worker->run(['sleep' => 0]);
        } finally {
            $dispatcher->removeListener(WorkerRunningEvent::class, $stop);
        }
    }

    private static function throwingListener(): Closure
    {
        return static function (): never {
            throw new RuntimeException('listener failed');
        };
    }

    /**
     * @return array{0: ContainerInterface, 1: EntityManagerInterface, 2: EventDispatcherInterface}
     */
    private function bootWithTenantPage(): array
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_builder']);
        $this->publisher($container)->createPageByKey('tenant-a', 'Tenant A', 'en');
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return [$container, $this->manager($container, 'page_builder'), $dispatcher];
    }

    /**
     * A request that loads a page through the bundle manager leaves nothing managed: the manager was cleared.
     */
    private function assertRequestClears(ContainerInterface $container, EntityManagerInterface $bundle, string $label): void
    {
        $kernel   = $this->httpKernel($container);
        $request  = Request::create('/t/show/tenant-a');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame('tenant-a|draft', $response->getContent(), $label);
        $kernel->terminate($request, $response);
        self::assertSame(0, $bundle->getUnitOfWork()->size(), $label);
    }

    private function input(string $command): ArrayInput
    {
        $input = new ArrayInput(['command' => $command]);
        $input->setInteractive(false);

        return $input;
    }

    private function application(): Application
    {
        self::assertNotNull($this->kernel);
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(true);

        return $application;
    }

    public function testDedicatedManagerThatIsTheHostDefaultIsNeverClearedByTheBundle(): void
    {
        $container = $this->boot($this->singleManager(), ['entity_manager' => 'default']);

        self::assertFalse($container->has(OwnEntityManagerSubscriber::class), 'rule 7: the host clears its own default manager');
    }

    public function testDefaultPathNeverResetsTheHostManager(): void
    {
        $container = $this->boot($this->singleManager(), []);
        $kernel    = $this->httpKernel($container);
        $default   = $this->manager($container, 'default');

        $request  = Request::create('/t/fail');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        $kernel->terminate($request, $response);
        self::assertFalse($default->isOpen());

        $request  = Request::create('/t/create/next');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(500, $response->getStatusCode(), 'the host owns its manager: the bundle does not reset it');
        self::assertFalse($default->isOpen());
    }

    /**
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed> $bundleDoctrine
     * @param (Closure(ManagerRegistry): void)|null $beforeSchema runs before any manager is initialized
     */
    private function boot(array $doctrine, array $bundleDoctrine, bool $revisions = false, bool $hostDefaultDriver = false, ?Closure $beforeSchema = null): ContainerInterface
    {
        $this->kernel = new PersistenceTestKernel($doctrine, $bundleDoctrine, $revisions, hostDefaultDriver: $hostDefaultDriver);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        if ($beforeSchema instanceof Closure) {
            $beforeSchema($registry);
        }
        foreach ($registry->getManagers() as $manager) {
            self::assertInstanceOf(EntityManagerInterface::class, $manager);
            $tool     = new SchemaTool($manager);
            $metadata = $manager->getMetadataFactory()->getAllMetadata();
            $tool->dropDatabase();
            $tool->createSchema($metadata);
        }

        $bundleManager = $container->get('nowo_page_builder_kit.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $bundleManager);
        $this->pageKeyColumn = $bundleManager->getClassMetadata(BuilderPage::class)->getColumnName('pageKey');
        $dispatcher          = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        foreach ([
            PageCreatedEvent::class,
            PageDocumentSavedEvent::class,
            PageMetadataSavedEvent::class,
            PagePublishedEvent::class,
            PageUnpublishedEvent::class,
            PageRevisionRestoredEvent::class,
            PageTemplateChangedEvent::class,
        ] as $eventClass) {
            $dispatcher->addListener($eventClass, function (object $event) use ($bundleManager): void {
                $this->events[]        = $event;
                $this->inTransaction[] = $bundleManager->getConnection()->isTransactionActive();
            });
        }

        return $container;
    }

    /**
     * The `http_kernel` service itself: FrankenPHP worker mode without kernel reset (Kernel::handle() would run the
     * services resetter between two requests).
     */
    private function publisher(ContainerInterface $container): PagePublisherInterface
    {
        $publisher = $container->get(PagePublisherInterface::class);
        self::assertInstanceOf(PagePublisherInterface::class, $publisher);

        return $publisher;
    }

    private function pending(ContainerInterface $container): PageBuilderPendingEventsInterface
    {
        $pending = $container->get(PageBuilderPendingEventsInterface::class);
        self::assertInstanceOf(PageBuilderPendingEventsInterface::class, $pending);

        return $pending;
    }

    /**
     * End of a request without a new request: `kernel.terminate` through the real dispatcher.
     */
    private function terminate(ContainerInterface $container): void
    {
        $this->httpKernel($container)->terminate(Request::create('/'), new Response());
    }

    /**
     * @return list<string>
     */
    private function eventKeys(): array
    {
        return array_map(static fn (object $event): string => $event instanceof PageCreatedEvent ? $event->pageKey : $event::class, $this->events);
    }

    private function commandEvent(Command $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, $this->commandInput($command), new NullOutput());
    }

    private function terminateEvent(Command $command): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent($command, $this->commandInput($command), new NullOutput(), 0);
    }

    /**
     * Symfony passes the same input to every event of one command run.
     */
    private function commandInput(Command $command): ArrayInput
    {
        return $this->commandInputs[$command] ??= new ArrayInput([]);
    }

    /**
     * Memory after a cycle collection (request / event objects form cycles freed only when the collector runs).
     */
    private function collectedMemory(): int
    {
        gc_collect_cycles();

        return memory_get_usage();
    }

    private function httpKernel(ContainerInterface $container): HttpKernel
    {
        $kernel = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $kernel);

        return $kernel;
    }

    /**
     * @return list<mixed>
     */
    private function pageKeys(EntityManagerInterface $manager): array
    {
        return $manager->getConnection()->fetchFirstColumn('SELECT ' . $this->pageKeyColumn . ' FROM pb_page ORDER BY id');
    }

    private function manager(ContainerInterface $container, string $name): EntityManagerInterface
    {
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * @return array<string, mixed>
     */
    private function hostMapping(): array
    {
        return [
            'Host' => [
                'type'      => 'attribute',
                'dir'       => dirname(__DIR__, 2) . '/Fixtures/Host',
                'prefix'    => 'Nowo\\PageBuilderKitBundle\\Tests\\Fixtures\\Host',
                'is_bundle' => false,
            ],
        ];
    }

    /**
     * Flex shorthand: one (default) entity manager.
     *
     * @return array<string, mixed>
     */
    private function singleManager(): array
    {
        return [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/default.sqlite'],
            'orm'  => ['mappings' => $this->hostMapping()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function twoManagers(bool $autoMapping, bool $defaultName = true): array
    {
        $orm = [
            'entity_managers' => [
                'default'      => ['connection' => 'default', 'auto_mapping' => $autoMapping, 'mappings' => $this->hostMapping()],
                'page_builder' => ['connection' => 'page_builder'],
            ],
        ];
        if ($defaultName) {
            $orm = ['default_entity_manager' => 'default'] + $orm;
        }

        return [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default'      => ['url' => 'sqlite:///%kernel.cache_dir%/default.sqlite'],
                    'page_builder' => ['url' => 'sqlite:///%kernel.cache_dir%/page_builder.sqlite'],
                ],
            ],
            'orm' => $orm,
        ];
    }
}
