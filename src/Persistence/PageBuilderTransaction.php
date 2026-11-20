<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Persistence;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\TransactionContract\TransactionRunnerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function array_values;
use function count;
use function is_string;
use function str_starts_with;

/**
 * Unit of work boundary of the bundle's writes (1.14, architecture bar rules 6 and 7).
 *
 * - `doctrine.transaction: bundle` (default, standard hosts): {@see commit()} flushes the entity manager that maps
 *   the bundle entities (`doctrine.entity_manager`, else the manager returned by `getManagerForClass()`), never the
 *   autowired default one by accident.
 * - `doctrine.transaction: host` (DDD hosts): {@see commit()} never flushes when the host calls the bundle; the
 *   host's use case owns the transaction and the events wait for its flush of that manager. The bundle's own admin
 *   controllers are a use case of their own: during a main request served by them the bundle still flushes.
 *
 * Events follow the **real** commit (LG-PBK-M1): once flushed they go to the {@see PageBuilderOutbox}, which
 * {@see AfterCommitMiddleware} tells about the transactions of the bundle's connection. A rollback (also a savepoint
 * rollback, or a host transaction around a bundle-mode write) drops them; the outermost commit makes them committed.
 * Committed events are dispatched at the next safe point where DBAL reports no open transaction: right after a
 * bundle flush or a host flush, {@see dispatchPending()}, and the end of the request / message / command (also a
 * failed one: a committed write keeps its event, LG-PBK-L1). Never inside a flush nor inside a transaction.
 *
 * Events of writes never flushed are discarded at the OUTERMOST boundary only (1.15.1, judged by
 * {@see UnitOfWorkBoundaries}, the same policy as `OwnEntityManagerSubscriber`), so a nested command or a request
 * handled inside a command never drops the outer unit's events; everything is dropped on `kernel.reset` (the nesting
 * state lives in `UnitOfWorkBoundaries`, which the services resetter does not touch). The start of an outermost main
 * request is a safety net for a runtime that never called `kernel.terminate`.
 *
 * Write units (1.17, REQ-RUNTIME-003 rule 1): a bundle write that reads before it writes, or writes several rows,
 * goes through {@see run()}: one transaction of nowo-tech/transaction-doctrine's runner on the bundle's manager, its
 * flushes inside it, nothing pending after a failure. {@see lockPage()} takes the page's row lock and reads the
 * document again under it (rule 4), so two editors never overwrite each other's read-modify-write. On the host's
 * manager the runner is `forHostEntityManager()` (an open manager is never closed, cleared or reset; a closed one is
 * replaced through the registry; it refuses to start while the manager holds unflushed changes); on the bundle's own
 * manager (`doctrine.entity_manager` other than the default) it is `forEntityManager()`.
 *
 * 1.19 (REQ-ARCH-001 rule 6 deprecation layer): with `doctrine.private_entity_manager: true` the manager is the
 * bundle's private one (transaction-doctrine `PrivateEntityManager`) and the runner `forEntityManager()` with a
 * `PrivateManagerRegistry`, so a failed unit drops only the private manager. While the option is unset or `false` on the
 * default manager, {@see probeHostFlush()} reports the application's pending changes before every flush of it.
 *
 * @internal Use {@see PageBuilderPendingEventsInterface}
 */
#[AsDoctrineListener(event: Events::postFlush)]
final class PageBuilderTransaction implements PageBuilderPendingEventsInterface, EventSubscriberInterface, ResetInterface
{
    private const string BUNDLE_CONTROLLER_NAMESPACE = 'Nowo\\PageBuilderKitBundle\\Controller\\';

    /** 1.19: the bundle name in the host-flush probe's messages and the private manager's logs. */
    public const string BUNDLE_NAME = 'NowoPageBuilderKitBundle';

    /** 1.19: the bundle's entity namespaces (never reported by the probe; routed to the private manager). */
    public const array OWN_NAMESPACES = ['Nowo\\PageBuilderKitBundle\\Entity'];

    /**
     * Events of writes not flushed yet (host-controlled transaction only).
     *
     * @var list<object>
     */
    // @igor-ignore - Request-scoped outbox: handed to the transaction outbox on flush, discarded at every boundary and reset().
    private array $unflushed = [];

    /** Depth of the {@see run()} calls in progress: a nested unit joins the outer one (always back to 0 when they end). */
    // @igor-ignore - Call-stack counter: incremented and decremented in the same call (finally), zeroed by reset().
    private int $runDepth = 0;

    /**
     * @param ManagerRegistry|null $registry Replaces a closed manager after a failed write unit
     * @param string|null $managerName Name of the bundle's manager in the registry (null: the default manager)
     * @param bool $ownsEntityManager Whether the manager is the bundle's own (`doctrine.entity_manager` other than the
     *                                host's default): a failed unit may then close and reset it
     * @param TransactionRunnerInterface|null $runner Runner of the write units (null: transaction-doctrine's, see above)
     * @param HostFlushProbe|null $hostFlushProbe 1.19: set only while `doctrine.private_entity_manager` is unset on the
     *                                            default (host) manager: reports the application's pending changes
     *                                            right before each bundle flush of that manager (read-only)
     * @param bool $resetsHostFlushProbe 1.19 (audit XD-8): whether this service resets the probe at its outermost
     *                                   boundaries and on reset(). False when the probe is transaction-doctrine's shared
     *                                   one and that bundle resets it at the same boundaries: the bundle then never
     *                                   clears what other bundles reported in the same unit of work
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly bool $hostControlsTransaction = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?RequestStack $requestStack = null,
        private readonly PageBuilderOutbox $outbox = new PageBuilderOutbox(),
        private readonly UnitOfWorkBoundaries $boundaries = new UnitOfWorkBoundaries(),
        private readonly ?ManagerRegistry $registry = null,
        private readonly ?string $managerName = null,
        private readonly bool $ownsEntityManager = false,
        private readonly ?TransactionRunnerInterface $runner = null,
        private readonly ?HostFlushProbe $hostFlushProbe = null,
        private readonly bool $resetsHostFlushProbe = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                                  => ['onHttpRequest', 4096],
            KernelEvents::TERMINATE                                => ['onHttpTerminate', 1024],
            UnitOfWorkBoundaries::CONSOLE_ERROR                    => ['onConsoleBoundary', -1024],
            UnitOfWorkBoundaries::CONSOLE_TERMINATE                => ['onConsoleBoundary', 1024],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED => ['onMessageFinished', 1024],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_FAILED  => ['onMessageFinished', -1024],
        ];
    }

    /**
     * Start of an outermost main request: safety net for a runtime that never called `kernel.terminate` after the
     * previous request. Committed events are dispatched and events of writes never flushed are discarded, so they
     * never reach the new request's unit of work (1.15.1). Nothing happens inside a command, a message or another main
     * request (audit NX CR2).
     */
    public function onHttpRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $this->boundaries->isOutermost($event)) {
            $this->onBoundary();
        }
    }

    /**
     * End of a request (also a failed one): a full boundary only outside a command and a Messenger message; an
     * in-process `HttpKernel::handle()` from a command or a handler only dispatches what was committed (1.15.1).
     *
     * `kernel.exception` is not a boundary since 1.15.1: FrameworkBundle's ErrorListener (priority -128) stops its
     * propagation, sub-requests share the main request's unit of work, and `kernel.terminate` follows every main
     * request, failed or not (LG-PBK-L1: a committed write keeps its event).
     */
    public function onHttpTerminate(TerminateEvent $event): void
    {
        $this->onBoundary($this->boundaries->isOutermost($event));
    }

    /**
     * Failure or end of a command: a full boundary only for the outermost command (outside a message and a main
     * request). An unknown command (no command on the error event) is the outermost unit only when nothing else runs.
     */
    public function onConsoleBoundary(object $event): void
    {
        $this->onBoundary($this->boundaries->isOutermost($event));
    }

    /**
     * End of a Messenger message (handled or failed): each message is its own unit of work; a message handled by a
     * worker run in-process inside another message's handler only dispatches what was committed (audit NX CR1).
     */
    public function onMessageFinished(object $event): void
    {
        $this->onBoundary($this->boundaries->isOutermost($event));
    }

    public function isHostControlled(): bool
    {
        return $this->hostControlsTransaction;
    }

    /**
     * Ends one bundle write: flushes the bundle entity manager (unless the host controls the transaction) and records
     * the write events, dispatched once the write is committed.
     */
    public function commit(object ...$events): void
    {
        $flush = $this->flushesItself();
        if ($flush) {
            $this->probeHostFlush();
            $this->entityManager->flush();
        }

        $this->record($flush, ...$events);
    }

    /**
     * Runs one write unit (1.17): one transaction of the bundle's connection, the bundle manager flushed before the
     * commit, the unit's events dispatched after it. A unit started inside another one joins it. When the host
     * controls the transaction ({@see flushesItself()} false) the operation runs as is: the host's use case owns it.
     *
     * @template T
     *
     * @param callable(): T $operation Loads (or {@see lockPage()}s) what it changes; one attempt, no replay
     *
     * @throws TransientStorageFailure When the storage failed transiently (deadlock, lock wait timeout, serialization
     *                                 failure): nothing of the unit is committed
     *
     * @return T
     */
    public function run(callable $operation): mixed
    {
        if ($this->runDepth > 0 || !$this->flushesItself()) {
            return $operation();
        }

        ++$this->runDepth;

        try {
            $result = ($this->runner ?? $this->defaultRunner())->run(function () use ($operation): mixed {
                $result = $operation();
                // The runner flushes the manager once more before its commit: report what that flush writes.
                $this->probeHostFlush();

                return $result;
            });
        } finally {
            --$this->runDepth;
        }

        // The unit's events were committed with it: dispatch them now, as a single flush outside a transaction did.
        $this->dispatchPending();

        return $result;
    }

    /**
     * After a failed write outside {@see run()} (the single flush of `DocumentService::flushPage()`): a manager the
     * failed flush closed is replaced through the registry, so the next request of a kept-alive worker can write
     * (1.17, REQ-RUNTIME-003 rule 2). An open manager is never touched. Inside a unit, or while a transaction is still
     * open on the connection, it does nothing: the runner (or the host that owns the transaction) recovers after its
     * rollback. It never throws: the caller rethrows the original failure.
     */
    public function replaceClosedManager(): void
    {
        if ($this->entityManager->isOpen() || !$this->registry instanceof ManagerRegistry || $this->runDepth > 0) {
            return;
        }

        try {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                return;
            }

            $this->logger->notice('Page builder: replacing the entity manager a failed write closed.', [
                'bundle'  => 'NowoPageBuilderKitBundle',
                'manager' => $this->managerName,
            ]);
            $this->registry->resetManager($this->managerName);
        } catch (Throwable $e) {
            $this->logger->error('Page builder: the entity manager a failed write closed could not be replaced.', [
                'bundle'    => 'NowoPageBuilderKitBundle',
                'manager'   => $this->managerName,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Inside a transaction: locks the page's document row (`SELECT … FOR UPDATE`; the page row when it has no
     * document yet) and reads the document and its locales again under the lock (1.17, REQ-RUNTIME-003 rule 4), so a
     * read-modify-write starts from the last committed content. Unsaved changes of the document are discarded; the
     * page's own fields and translations are not read again. Outside a transaction (host-controlled writes with no
     * open transaction), or for a page not stored yet, it does nothing.
     */
    public function lockPage(BuilderPage $page): void
    {
        if ($page->getId() === null || !$this->entityManager->getConnection()->isTransactionActive() || !$this->entityManager->contains($page)) {
            return;
        }

        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument || $document->getId() === null || !$this->entityManager->contains($document)) {
            $this->entityManager->lock($page, LockMode::PESSIMISTIC_WRITE);

            return;
        }

        $this->entityManager->refresh($document, LockMode::PESSIMISTIC_WRITE);
        foreach ($document->getLocales() as $locale) {
            if ($locale->getId() !== null && $this->entityManager->contains($locale)) {
                $this->entityManager->refresh($locale);
            }
        }
    }

    /**
     * Records the events of writes already handed to {@see commit()} (e.g. a revision restore on top of a save).
     */
    public function record(?bool $flushed = null, object ...$events): void
    {
        if ($events === []) {
            return;
        }

        if (!($flushed ?? $this->flushesItself())) {
            $this->unflushed = [...$this->unflushed, ...array_values($events)];

            return;
        }

        $this->outbox->hold(...$events);
        $this->dispatchPending();
    }

    /**
     * Whether the bundle flushes its own writes: always in `bundle` mode, and in `host` mode only while one of the
     * bundle's admin controllers serves the main request (the bundle UI owns that unit of work).
     */
    public function flushesItself(): bool
    {
        if (!$this->hostControlsTransaction) {
            return true;
        }

        $controller = $this->requestStack?->getMainRequest()?->attributes->get('_controller');

        return is_string($controller) && str_starts_with($controller, self::BUNDLE_CONTROLLER_NAMESPACE);
    }

    /**
     * Dispatches the events of committed writes when DBAL reports no open transaction on the bundle's connection.
     * A listener that throws cannot undo the commit: it is logged and the other events are still dispatched.
     */
    public function dispatchPending(): int
    {
        if ($this->outbox->countCommitted() === 0 || $this->entityManager->getConnection()->isTransactionActive()) {
            return 0;
        }

        $events = $this->outbox->takeCommitted();
        foreach ($events as $event) {
            try {
                $this->eventDispatcher?->dispatch($event);
            } catch (Throwable $e) {
                $this->logger->error('Page builder: a listener failed after the commit.', [
                    'bundle'    => 'NowoPageBuilderKitBundle',
                    'event'     => $event::class,
                    'exception' => $e::class,
                ]);
            }
        }

        return count($events);
    }

    public function countPending(): int
    {
        return count($this->unflushed) + $this->outbox->countHeld() + $this->outbox->countCommitted();
    }

    /**
     * Doctrine `postFlush`: a flush of the bundle manager wrote the recorded changes; their events now follow the
     * transaction (if any) that flush ran in.
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->unflushed !== [] && $args->getObjectManager() === $this->entityManager) {
            $events          = $this->unflushed;
            $this->unflushed = [];
            $this->outbox->hold(...$events);
        }

        $this->dispatchPending();
    }

    /**
     * End of a request, message or command (also a failed one): dispatches what was committed and, at the OUTERMOST
     * boundary only, discards the events of writes never flushed. A nested boundary (a command run from another
     * command, a handler or a controller; a request handled inside a command or a message) keeps them for the outer
     * unit of work, whose use case may still flush (1.15.1).
     */
    public function onBoundary(bool $outermost = true): void
    {
        try {
            $this->dispatchPending();
        } finally {
            if ($outermost) {
                $this->discardUnflushed();
                // 1.19: a new unit of work reports its classes again (also without transaction-doctrine's bundle).
                $this->resetHostFlushProbe();
            }
        }
    }

    /**
     * Drops the events of writes never flushed.
     */
    public function discardUnflushed(): void
    {
        $count = count($this->unflushed);
        if ($count === 0) {
            return;
        }

        $this->logger->warning('Page builder write events discarded: the write was never flushed before the end of the request, message or command.', [
            'bundle' => 'NowoPageBuilderKitBundle',
            'events' => $count,
        ]);
        $this->unflushed = [];
    }

    public function reset(): void
    {
        $this->unflushed = [];
        $this->runDepth  = 0;
        $this->outbox->reset();
        $this->resetHostFlushProbe();
    }

    /**
     * 1.19 (REQ-ARCH-001 rule 6 deprecation layer): right before the bundle flushes the host's manager, one
     * `E_USER_DEPRECATED` per application entity class with pending changes per unit of work ("Your pending changes to
     * App\X were flushed by NowoPageBuilderKitBundle; 2.0 no longer does."). Read-only, and it never breaks a write: a
     * probe failure is logged and the flush goes on.
     */
    private function probeHostFlush(): void
    {
        if (!$this->hostFlushProbe instanceof HostFlushProbe) {
            return;
        }

        try {
            $this->hostFlushProbe->beforeHostFlush($this->entityManager, self::BUNDLE_NAME, self::OWN_NAMESPACES);
        } catch (Throwable $e) {
            $this->logger->warning('Page builder: the host-flush probe failed; the write goes on.', [
                'bundle'    => self::BUNDLE_NAME,
                'exception' => $e::class,
            ]);
        }
    }

    /** The bundle's own probe only: transaction-doctrine resets its shared probe itself (audit XD-8). */
    private function resetHostFlushProbe(): void
    {
        if ($this->resetsHostFlushProbe) {
            $this->hostFlushProbe?->reset();
        }
    }

    /**
     * No Retry-After hint of its own: the conflict answer uses `conflict_response.retry_after`.
     */
    private function defaultRunner(): TransactionRunnerInterface
    {
        return $this->ownsEntityManager
            ? DoctrineTransactionRunner::forEntityManager($this->entityManager, $this->registry, $this->managerName, null, $this->logger)
            : DoctrineTransactionRunner::forHostEntityManager($this->entityManager, $this->registry, $this->managerName, null, $this->logger);
    }
}
