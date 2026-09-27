<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\EventSubscriber;

use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function is_string;

final readonly class PageBuilderKitAdminAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PageBuilderKitAccessCheckerInterface $accessChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if (!is_string($route)) {
            return;
        }

        if (str_starts_with($route, 'admin_page_builder_') && !$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Page builder admin requires an authorized user.');
        }
    }
}
