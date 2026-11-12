<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Debug;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Clears the debug trace at every unit-of-work boundary, without relying on the services resetter (REQ-RUNTIME-002).
 *
 * The trace is otherwise cleared only by `kernel.reset`, which does not run when a host removes or bypasses
 * `services_resetter` (or drives `http_kernel` directly), in Messenger consumers started with `--no-reset`, or in
 * long-running console commands. Boundaries: the main `kernel.request` (start of the request, so the profiler still
 * collects the finished request), Messenger received / handled / failed, and `console.terminate`.
 *
 * The traces arrive as a lazy iterator of `IGNORE_ON_UNINITIALIZED_REFERENCE` references: a boundary never builds the
 * trace, it only clears an instance that already exists.
 */
final readonly class PageBuilderKitTraceBoundarySubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<ResetInterface> $traces
     */
    public function __construct(
        private iterable $traces,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request'                  => ['onMainRequest', 4096],
            WorkerMessageReceivedEvent::class => ['clear', 4096],
            WorkerMessageHandledEvent::class  => ['clear', -4096],
            WorkerMessageFailedEvent::class   => ['clear', -4096],
            // String names: symfony/console and symfony/messenger are optional for this bundle.
            'console.terminate' => ['clear', -4096],
        ];
    }

    public function onMainRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->clear();
    }

    public function clear(): void
    {
        foreach ($this->traces as $trace) {
            $trace->reset();
        }
    }
}
