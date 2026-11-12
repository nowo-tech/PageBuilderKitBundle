<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function max;

/**
 * Keeps the bundle's OWN entity manager (`doctrine.entity_manager`) usable in a kept-alive kernel (1.14; outermost
 * boundaries since 1.15.1).
 *
 * Registered only when `doctrine.entity_manager` is set: that manager maps the bundle entities only, so the bundle
 * recreates it when a failed flush closed it and clears it at every OUTERMOST unit-of-work boundary, so no managed page
 * survives into the next request, message or command (REQ-RUNTIME-002 rule 4). The host's managers are never reset
 * nor cleared (rule 7: the host clears its own managers).
 *
 * Boundaries (same policy as ContactFormBundle 1.7.1 `ContactFormEntityManagerClearSubscriber`):
 * - HTTP: the main `kernel.request` (priority 4096) and `kernel.terminate` (priority -1024), only outside a console
 *   command and a Messenger message. An in-process `HttpKernel::handle()` from a command or a handler does not clear
 *   the unit that runs it; sub-requests share their main request's unit of work;
 * - Messenger: when a message is received (priority 4096) and after it is handled or failed (priority -1024);
 * - console: when the OUTERMOST command starts and terminates, only outside a Messenger message and outside a main
 *   request. A command run in-process from another command, a message handler or a controller does not clear the
 *   outer unit's manager (with `transaction: host` that would discard the host's pending changes).
 *
 * State: a console depth counter; a message flag set on received and unset on handled / failed / worker stopped; a
 * request flag set on the main `kernel.request` and unset on its `kernel.finish_request`.
 *
 * The manager and the registry arrive as iterators of IGNORE_ON_UNINITIALIZED_REFERENCE references, so a boundary only
 * touches a manager the unit of work already built and never builds one itself. The Messenger and console events are
 * subscribed by name, so symfony/messenger and symfony/console stay optional.
 *
 * @internal registered by the bundle; the constructor is not a public API
 */
final class OwnEntityManagerSubscriber implements EventSubscriberInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const string MESSENGER_WORKER_MESSAGE_RECEIVED = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent';
    public const string MESSENGER_WORKER_MESSAGE_HANDLED  = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent';
    public const string MESSENGER_WORKER_MESSAGE_FAILED   = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent';
    public const string MESSENGER_WORKER_STOPPED          = 'Symfony\\Component\\Messenger\\Event\\WorkerStoppedEvent';

    /** Console event names (ConsoleEvents::COMMAND / TERMINATE). */
    public const string CONSOLE_COMMAND   = 'console.command';
    public const string CONSOLE_TERMINATE = 'console.terminate';

    /**
     * Console commands currently running in this process (nested in-process runs count). Process-lifetime counter,
     * bounded by the nesting depth; intentionally not reset by `kernel.reset`.
     */
    private int $consoleDepth = 0;

    /** A Messenger message is being handled (between received and handled / failed / worker stopped). */
    private bool $messageInFlight = false;

    /** A main request is being handled (between its `kernel.request` and its `kernel.finish_request`). */
    private bool $requestInFlight = false;

    /**
     * @param iterable<EntityManagerInterface> $entityManagers the bundle-owned manager (skipped while uninitialized)
     * @param iterable<ManagerRegistry> $registries the Doctrine registry, to recreate a closed manager
     * @param string $managerName `doctrine.entity_manager`
     */
    public function __construct(
        private readonly iterable $entityManagers,
        private readonly iterable $registries,
        private readonly string $managerName,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onRequest', 4096],
            KernelEvents::FINISH_REQUEST            => ['onFinishRequest', -1024],
            KernelEvents::TERMINATE                 => ['onTerminate', -1024],
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['onMessageReceived', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['onMessageFinished', -1024],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['onMessageFinished', -1024],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', -1024],
            self::CONSOLE_COMMAND                   => ['onConsoleCommand', 4096],
            self::CONSOLE_TERMINATE                 => ['onConsoleTerminate', -1024],
        ];
    }

    /**
     * Start of a main request: recovers / clears only outside a command and a message.
     */
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $outermost = $this->consoleDepth === 0 && !$this->messageInFlight;
        // @igor-ignore - Unit-of-work flag, set and unset at the main request boundaries.
        $this->requestInFlight = true;
        if ($outermost) {
            $this->clearState();
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
     * End of a request: clears only outside a command and a message (an in-process HttpKernel::handle()).
     */
    public function onTerminate(): void
    {
        if ($this->consoleDepth === 0 && !$this->messageInFlight) {
            $this->clearState();
        }
    }

    /**
     * Start of a Messenger message: nothing of the previous message or of the worker's idle time is kept.
     */
    public function onMessageReceived(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = true;
        $this->clearState();
    }

    /**
     * End of a Messenger message (handled or failed).
     */
    public function onMessageFinished(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
        $this->clearState();
    }

    /**
     * The worker stopped (possibly after a skipped message): no message is in flight any more.
     */
    public function onWorkerStopped(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
    }

    /**
     * Start of a command: clears only for the outermost command outside a message and a main request.
     */
    public function onConsoleCommand(): void
    {
        if ($this->isOutermostUnit()) {
            $this->clearState();
        }
        // @igor-ignore - Nesting counter of the running commands (no request data).
        ++$this->consoleDepth;
    }

    /**
     * End of a command: clears only for the outermost command outside a message and a main request.
     */
    public function onConsoleTerminate(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        $this->consoleDepth = max(0, $this->consoleDepth - 1);
        if ($this->isOutermostUnit()) {
            $this->clearState();
        }
    }

    /**
     * Recreates the bundle's manager when a failed write closed it, clears it when it is open (unflushed changes of
     * the finished unit are discarded); nothing when it was never built.
     */
    public function clearState(): void
    {
        foreach ($this->entityManagers as $entityManager) {
            if ($entityManager->isOpen()) {
                // @igor-ignore - This IS the unit-of-work boundary reset of the bundle's own manager (no state kept).
                $entityManager->clear();

                continue;
            }

            foreach ($this->registries as $registry) {
                $registry->resetManager($this->managerName);
            }
        }
    }

    private function isOutermostUnit(): bool
    {
        return $this->consoleDepth === 0 && !$this->messageInFlight && !$this->requestInFlight;
    }
}
