<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Persistence;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function array_values;
use function count;
use function is_string;
use function max;
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
 * Events of writes never flushed are discarded at the OUTERMOST boundary only (1.15.1: a console depth counter and
 * Messenger / main-request in-flight flags, the same policy as `OwnEntityManagerSubscriber`), so a nested command or a
 * request handled inside a command never drops the outer unit's events; everything is dropped on `kernel.reset`.
 *
 * @internal Use {@see PageBuilderPendingEventsInterface}
 */
#[AsDoctrineListener(event: Events::postFlush)]
final class PageBuilderTransaction implements PageBuilderPendingEventsInterface, EventSubscriberInterface, ResetInterface
{
    private const string BUNDLE_CONTROLLER_NAMESPACE = 'Nowo\\PageBuilderKitBundle\\Controller\\';

    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const string MESSENGER_WORKER_MESSAGE_RECEIVED = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent';
    public const string MESSENGER_WORKER_MESSAGE_HANDLED  = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent';
    public const string MESSENGER_WORKER_MESSAGE_FAILED   = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent';
    public const string MESSENGER_WORKER_STOPPED          = 'Symfony\\Component\\Messenger\\Event\\WorkerStoppedEvent';

    /**
     * Events of writes not flushed yet (host-controlled transaction only).
     *
     * @var list<object>
     */
    // @igor-ignore - Request-scoped outbox: handed to the transaction outbox on flush, discarded at every boundary and reset().
    private array $unflushed = [];

    /**
     * Console commands currently running in this process (nested in-process runs count), bounded by the nesting depth
     * (1.15.1). `reset()` also zeroes it, like the flags: the services resetter already drops every pending event, so
     * a reset in the middle of a command cannot lose more than it does today.
     */
    private int $consoleDepth = 0;

    /** A Messenger message is being handled (between received and handled / failed / worker stopped). */
    private bool $messageInFlight = false;

    /** A main request is being handled (between its `kernel.request` and its `kernel.finish_request`). */
    private bool $requestInFlight = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly bool $hostControlsTransaction = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?RequestStack $requestStack = null,
        private readonly PageBuilderOutbox $outbox = new PageBuilderOutbox(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST        => ['onRequest', 4096],
            KernelEvents::FINISH_REQUEST => ['onFinishRequest', -1024],
            KernelEvents::EXCEPTION      => ['onHttpBoundary', -1024],
            KernelEvents::TERMINATE      => ['onHttpBoundary', 1024],
            ConsoleEvents::COMMAND       => ['onConsoleCommand', 4096],
            ConsoleEvents::ERROR         => ['onConsoleError', -1024],
            ConsoleEvents::TERMINATE     => ['onConsoleTerminate', 1024],
            // symfony/messenger is optional: event names as strings.
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['onMessageReceived', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['onMessageFinished', 1024],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['onMessageFinished', -1024],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', -1024],
        ];
    }

    /**
     * Start of a main request: from now on a command run in-process from it is nested in its unit of work (1.15.1).
     */
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            // @igor-ignore - Unit-of-work flag, set and unset at the main request boundaries.
            $this->requestInFlight = true;
        }
    }

    public function onFinishRequest(FinishRequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            // @igor-ignore - Unit-of-work flag, set and unset at the main request boundaries.
            $this->requestInFlight = false;
        }
    }

    /**
     * End (or failure) of a request: a full boundary only outside a command and a Messenger message; an in-process
     * `HttpKernel::handle()` from a command or a handler only dispatches what was committed (1.15.1).
     */
    public function onHttpBoundary(): void
    {
        $this->onBoundary($this->consoleDepth === 0 && !$this->messageInFlight);
    }

    public function onConsoleCommand(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        ++$this->consoleDepth;
    }

    /**
     * Failure of a command: a full boundary only for the outermost command outside a message and a main request.
     */
    public function onConsoleError(): void
    {
        $this->onBoundary($this->consoleDepth <= 1 && !$this->messageInFlight && !$this->requestInFlight);
    }

    /**
     * End of a command: a full boundary only for the outermost command outside a message and a main request.
     */
    public function onConsoleTerminate(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        $this->consoleDepth = max(0, $this->consoleDepth - 1);
        $this->onBoundary($this->consoleDepth === 0 && !$this->messageInFlight && !$this->requestInFlight);
    }

    public function onMessageReceived(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = true;
    }

    /**
     * End of a Messenger message (handled or failed): each message is its own unit of work.
     */
    public function onMessageFinished(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
        $this->onBoundary();
    }

    /**
     * The worker stopped (possibly after a skipped message): no message is in flight any more.
     */
    public function onWorkerStopped(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
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
            $this->entityManager->flush();
        }

        $this->record($flush, ...$events);
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
        $this->unflushed       = [];
        $this->consoleDepth    = 0;
        $this->messageInFlight = false;
        $this->requestInFlight = false;
        $this->outbox->reset();
    }
}
