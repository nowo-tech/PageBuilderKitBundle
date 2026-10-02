<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\EventSubscriber;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\EventSubscriber\PageBuilderKitAdminAccessSubscriber;
use Nowo\PageBuilderKitBundle\Security\AllowAllPageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[CoversClass(PageBuilderKitAdminAccessSubscriber::class)]
final class PageBuilderKitAdminAccessSubscriberTest extends TestCase
{
    #[Test]
    public function subscribedEvents(): void
    {
        self::assertArrayHasKey('kernel.controller', PageBuilderKitAdminAccessSubscriber::getSubscribedEvents());
    }

    #[Test]
    public function allowsAdminRouteWhenCheckerPermits(): void
    {
        $subscriber = new PageBuilderKitAdminAccessSubscriber(new AllowAllPageBuilderKitAccessChecker());
        $event      = $this->controllerEvent('admin_page_builder_list');

        $subscriber->onKernelController($event);

        self::assertSame('admin_page_builder_list', $event->getRequest()->attributes->get('_route'));
    }

    #[Test]
    public function deniesAdminRouteWhenCheckerRejects(): void
    {
        $subscriber = new PageBuilderKitAdminAccessSubscriber($this->denyAllChecker());

        $this->expectException(AccessDeniedException::class);

        $subscriber->onKernelController($this->controllerEvent('admin_page_builder_edit'));
    }

    #[Test]
    public function deniesCanvasWithoutLayoutCapability(): void
    {
        $checker = new class implements PageBuilderKitAccessCheckerInterface {
            public function canAccess(): bool
            {
                return true;
            }

            public function canLayout(): bool
            {
                return false;
            }

            public function canContent(): bool
            {
                return true;
            }

            public function canPublish(): bool
            {
                return false;
            }

            public function canTemplates(): bool
            {
                return false;
            }

            public function can(PageBuilderCapability|string $capability): bool
            {
                return $capability === 'content' || $capability === PageBuilderCapability::Content;
            }
        };

        $subscriber = new PageBuilderKitAdminAccessSubscriber($checker);

        $this->expectException(AccessDeniedException::class);
        $subscriber->onKernelController($this->controllerEvent('admin_page_builder_canvas'));
    }

    #[Test]
    public function ignoresNonAdminRoutesAndMissingRoute(): void
    {
        $calls        = new stdClass();
        $calls->count = 0;
        $checker      = new class($calls) implements PageBuilderKitAccessCheckerInterface {
            public function __construct(private readonly stdClass $calls)
            {
            }

            public function canAccess(): bool
            {
                ++$this->calls->count;

                return false;
            }

            public function canLayout(): bool
            {
                return false;
            }

            public function canContent(): bool
            {
                return false;
            }

            public function canPublish(): bool
            {
                return false;
            }

            public function canTemplates(): bool
            {
                return false;
            }

            public function can(PageBuilderCapability|string $capability): bool
            {
                return false;
            }
        };
        $subscriber = new PageBuilderKitAdminAccessSubscriber($checker);

        $subscriber->onKernelController($this->controllerEvent('public_home'));
        $subscriber->onKernelController($this->controllerEvent(null));

        self::assertSame(0, $calls->count);
    }

    private function denyAllChecker(): PageBuilderKitAccessCheckerInterface
    {
        return new class implements PageBuilderKitAccessCheckerInterface {
            public function canAccess(): bool
            {
                return false;
            }

            public function canLayout(): bool
            {
                return false;
            }

            public function canContent(): bool
            {
                return false;
            }

            public function canPublish(): bool
            {
                return false;
            }

            public function canTemplates(): bool
            {
                return false;
            }

            public function can(PageBuilderCapability|string $capability): bool
            {
                return false;
            }
        };
    }

    private function controllerEvent(?string $route): ControllerEvent
    {
        $kernel  = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }

        return new ControllerEvent($kernel, static fn (): null => null, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
