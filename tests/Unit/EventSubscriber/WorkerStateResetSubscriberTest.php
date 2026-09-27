<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\EventSubscriber;

use Nowo\PageBuilderKitBundle\EventSubscriber\WorkerStateResetSubscriber;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocalesLegacyBinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(WorkerStateResetSubscriber::class)]
final class WorkerStateResetSubscriberTest extends TestCase
{
    #[Test]
    public function getSubscribedEvents(): void
    {
        self::assertArrayHasKey('kernel.terminate', WorkerStateResetSubscriber::getSubscribedEvents());
    }

    #[Test]
    public function clearsBoundLocalesOnMainRequestTerminate(): void
    {
        $binding = new BuilderLocalesLegacyBinding();
        $binding->bind(new BuilderLocales('es', ['es']));

        $subscriber = new WorkerStateResetSubscriber($binding);
        $kernel     = $this->createStub(HttpKernelInterface::class);
        $event      = new TerminateEvent($kernel, new Request(), new Response());

        $subscriber->onTerminate($event);

        self::assertNull($binding->getBoundInstance());
    }
}
