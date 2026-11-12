<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Persistence;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use WeakMap;

use function array_splice;
use function count;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Tracks which unit of work is running in this process, so the bundle acts only at the OUTERMOST boundary of a
 * request, a Messenger message or a console command (1.15.1, REQ-RUNTIME-002 rule 4). Used by
 * {@see OwnEntityManagerSubscriber} (clears the bundle's own manager) and {@see PageBuilderTransaction} (discards the
 * events of unflushed writes).
 *
 * The state changes at priority `PHP_INT_MAX`, before any other listener can throw:
 * - console: a stack of the running commands; each entry remembers whether its command was the outermost unit when it
 *   started. `console.terminate` removes the entry of ITS command (and the entries above it, nested commands that
 *   never terminated); a terminate without a matching entry never removes another command's entry;
 * - `console.error` marks the entry as failing and the end of the error listeners (priority `PHP_INT_MIN`) unmarks it.
 *   When an error listener throws, Symfony never dispatches the command's `console.terminate`: the entry stays
 *   marked and is dropped at the next request, message, command or worker stop (self-healing, so the process never
 *   stays "inside a command" for good);
 * - a main request in flight (main `kernel.request` → `kernel.finish_request`) and a Messenger message in flight
 *   (received → handled / failed / skipped / worker stopped).
 *
 * The verdict for each console event ("was this the outermost unit?") is kept per event object (`WeakMap`), so the
 * late listeners that act on it read the value computed when the event started, whatever ran in between.
 *
 * Process-lifetime state, deliberately NOT reset by `kernel.reset`: it describes the units still running.
 *
 * @internal
 */
final class UnitOfWorkBoundaries implements EventSubscriberInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const string MESSENGER_WORKER_MESSAGE_RECEIVED = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent';
    public const string MESSENGER_WORKER_MESSAGE_HANDLED  = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent';
    public const string MESSENGER_WORKER_MESSAGE_FAILED   = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent';
    public const string MESSENGER_WORKER_MESSAGE_SKIPPED  = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageSkipEvent';
    public const string MESSENGER_WORKER_STOPPED          = 'Symfony\\Component\\Messenger\\Event\\WorkerStoppedEvent';

    /** Console event names (ConsoleEvents::COMMAND / ERROR / TERMINATE). */
    public const string CONSOLE_COMMAND   = 'console.command';
    public const string CONSOLE_ERROR     = 'console.error';
    public const string CONSOLE_TERMINATE = 'console.terminate';

    /** @var list<array{command: object, outermost: bool, failing: bool}> running commands, outermost first */
    private array $commands = [];

    private bool $messageInFlight = false;

    private bool $requestInFlight = false;

    /** @var WeakMap<object, bool> outermost verdict per console event */
    private WeakMap $verdicts;

    public function __construct()
    {
        $this->verdicts = new WeakMap();
    }

    /**
     * @return array<string, array{0: string, 1: int}|list<array{0: string, 1: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onRequest', PHP_INT_MAX],
            KernelEvents::FINISH_REQUEST            => ['onFinishRequest', PHP_INT_MAX],
            self::CONSOLE_COMMAND                   => ['onCommand', PHP_INT_MAX],
            self::CONSOLE_ERROR                     => [['onErrorStarted', PHP_INT_MAX], ['onErrorHandled', PHP_INT_MIN]],
            self::CONSOLE_TERMINATE                 => ['onTerminate', PHP_INT_MAX],
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['onMessageReceived', PHP_INT_MAX],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['onMessageFinished', PHP_INT_MAX],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['onMessageFinished', PHP_INT_MAX],
            self::MESSENGER_WORKER_MESSAGE_SKIPPED  => ['onMessageFinished', PHP_INT_MAX],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', PHP_INT_MAX],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->dropFailedCommands();
        // @igor-ignore - Unit-of-work flag, set and unset at the main request boundaries.
        $this->requestInFlight = true;
    }

    public function onFinishRequest(FinishRequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            // @igor-ignore - Unit-of-work flag, set and unset at the main request boundaries.
            $this->requestInFlight = false;
        }
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->dropFailedCommands();
        $outermost = $this->isIdle();
        // @igor-ignore - Per-event verdict; the WeakMap entry dies with the event.
        $this->verdicts[$event] = $outermost;
        // @igor-ignore - Stack of the running commands (no request data), popped by their own terminate.
        $this->commands[] = ['command' => $event->getCommand() ?? $event, 'outermost' => $outermost, 'failing' => false];
    }

    /**
     * A command failed (or was not found: no command, no entry). Its verdict is its entry's; without an entry it is
     * the outermost unit only when nothing else runs.
     */
    public function onErrorStarted(ConsoleErrorEvent $event): void
    {
        $index = $this->indexOf($event->getCommand());
        if ($index === null) {
            // @igor-ignore - Per-event verdict; the WeakMap entry dies with the event.
            $this->verdicts[$event] = $this->isIdle();

            return;
        }

        // @igor-ignore - Per-event verdict; the WeakMap entry dies with the event.
        $this->verdicts[$event] = $this->commands[$index]['outermost'];
        // @igor-ignore - Failure marker of a running command, cleared when the error listeners end.
        $this->commands[$index]['failing'] = true;
    }

    /**
     * Every error listener ran: Symfony will dispatch the command's terminate.
     */
    public function onErrorHandled(ConsoleErrorEvent $event): void
    {
        $index = $this->indexOf($event->getCommand());
        if ($index !== null) {
            // @igor-ignore - Failure marker of a running command, cleared when the error listeners end.
            $this->commands[$index]['failing'] = false;
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $index = $this->indexOf($event->getCommand());
        if ($index === null) {
            $this->dropFailedCommands();
            // @igor-ignore - Per-event verdict; the WeakMap entry dies with the event.
            $this->verdicts[$event] = $this->isIdle();

            return;
        }

        // @igor-ignore - Per-event verdict; the WeakMap entry dies with the event.
        $this->verdicts[$event] = $this->commands[$index]['outermost'];
        // Its own entry and the nested commands above it that never terminated.
        // @igor-ignore - Stack of the running commands (no request data), popped by their own terminate.
        array_splice($this->commands, $index);
    }

    public function onMessageReceived(): void
    {
        $this->dropFailedCommands();
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = true;
    }

    public function onMessageFinished(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
    }

    public function onWorkerStopped(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
        $this->dropFailedCommands();
    }

    /**
     * Whether the console event started or ended the outermost unit of work. An event this tracker did not see is
     * judged on the current state.
     */
    public function isOutermost(object $consoleEvent): bool
    {
        return $this->verdicts[$consoleEvent] ?? $this->isIdle();
    }

    /**
     * A main request is the outermost unit unless a command or a message runs it in-process.
     */
    public function isHttpOutermost(): bool
    {
        return $this->commands === [] && !$this->messageInFlight;
    }

    public function runningCommands(): int
    {
        return count($this->commands);
    }

    private function isIdle(): bool
    {
        return $this->commands === [] && !$this->messageInFlight && !$this->requestInFlight;
    }

    private function indexOf(?object $command): ?int
    {
        if ($command === null) {
            return null;
        }

        for ($i = count($this->commands) - 1; $i >= 0; --$i) {
            if ($this->commands[$i]['command'] === $command) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Drops the commands whose error listeners threw (Symfony never dispatches their terminate), with the nested
     * entries above them.
     */
    private function dropFailedCommands(): void
    {
        foreach ($this->commands as $i => $entry) {
            if ($entry['failing']) {
                // @igor-ignore - Stack of the running commands (no request data), self-healing after a failed error listener.
                array_splice($this->commands, $i);

                return;
            }
        }
    }
}
