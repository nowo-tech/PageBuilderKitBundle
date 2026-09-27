<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\EventSubscriber;

use Nowo\PageBuilderKitBundle\Locale\BuilderLocalesLegacyBinding;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Clears optional bound locale state after each request (FrankenPHP worker safety).
 */
final class WorkerStateResetSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BuilderLocalesLegacyBinding $legacyBinding,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        unset($event);
        // TerminateEvent is always a main request in Symfony; reset unconditionally for worker safety.
        $this->legacyBinding->reset();
    }
}
