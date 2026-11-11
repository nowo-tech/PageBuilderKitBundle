<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Debug;

use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceBoundarySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(PageBuilderKitTraceBoundarySubscriber::class)]
final class PageBuilderKitTraceBoundarySubscriberTest extends TestCase
{
    #[Test]
    public function subscribesToEveryBoundary(): void
    {
        $events = PageBuilderKitTraceBoundarySubscriber::getSubscribedEvents();

        self::assertSame(['onMainRequest', 4096], $events['kernel.request']);
        self::assertSame('clear', $events['Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent'][0]);
        self::assertSame('clear', $events['Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent'][0]);
        self::assertSame('clear', $events['Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent'][0]);
        self::assertSame('clear', $events['console.terminate'][0]);
    }

    #[Test]
    public function mainRequestClearsAndSubRequestKeepsTheTrace(): void
    {
        $trace      = $this->filledTrace();
        $subscriber = new PageBuilderKitTraceBoundarySubscriber([$trace]);
        $kernel     = $this->createStub(HttpKernelInterface::class);

        $subscriber->onMainRequest(new RequestEvent($kernel, Request::create('/fragment'), HttpKernelInterface::SUB_REQUEST));
        self::assertCount(1, $trace->getRenders(), 'a sub-request belongs to the same unit of work');

        $subscriber->onMainRequest(new RequestEvent($kernel, Request::create('/next'), HttpKernelInterface::MAIN_REQUEST));
        self::assertSame([], $trace->getRenders());
        self::assertSame([], $trace->getPublicOutcomes());
        self::assertSame([], $trace->getAdminActions());
    }

    #[Test]
    public function boundaryNeverBuildsTheTraceAndClearsItOnceBuilt(): void
    {
        $container = new ContainerBuilder();
        $container->register(PageBuilderKitTrace::class)->setPublic(true);
        $container->register(PageBuilderKitTraceBoundarySubscriber::class)
            ->setPublic(true)
            ->setArguments([
                new IteratorArgument([new Reference(PageBuilderKitTrace::class, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)]),
            ]);
        $container->compile();

        $subscriber = $container->get(PageBuilderKitTraceBoundarySubscriber::class);
        self::assertInstanceOf(PageBuilderKitTraceBoundarySubscriber::class, $subscriber);
        $subscriber->clear();
        self::assertFalse($container->initialized(PageBuilderKitTrace::class), 'the boundary does not build the trace');

        $trace = $container->get(PageBuilderKitTrace::class);
        self::assertInstanceOf(PageBuilderKitTrace::class, $trace);
        $trace->addPublicOutcome('home', 'published');
        $subscriber->clear();
        self::assertSame([], $trace->getPublicOutcomes(), 'the same subscriber instance clears the trace built later');
    }

    private function filledTrace(): PageBuilderKitTrace
    {
        $trace = new PageBuilderKitTrace();
        $trace->addRender(['pageKey' => 'home', 'locale' => 'en', 'status' => 'published', 'engine' => 'grapesjs']);
        $trace->addPublicOutcome('home', 'published', 'en');
        $trace->addAdminAction('save', 'home', 1);

        return $trace;
    }
}
