<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * REQ-RUNTIME-002 rule 4 (1.15.1): the bundle's own manager is cleared only at the OUTERMOST unit-of-work boundary.
 */
#[CoversClass(OwnEntityManagerSubscriber::class)]
final class OwnEntityManagerSubscriberTest extends TestCase
{
    private int $clears = 0;

    #[Test]
    public function subscribesToEveryUnitOfWorkBoundary(): void
    {
        $events = OwnEntityManagerSubscriber::getSubscribedEvents();

        self::assertSame(['onRequest', 4096], $events[KernelEvents::REQUEST]);
        self::assertSame(['onFinishRequest', -1024], $events[KernelEvents::FINISH_REQUEST]);
        self::assertSame(['onTerminate', -1024], $events[KernelEvents::TERMINATE]);
        self::assertSame(['onMessageReceived', 4096], $events[OwnEntityManagerSubscriber::MESSENGER_WORKER_MESSAGE_RECEIVED]);
        self::assertSame(['onMessageFinished', -1024], $events[OwnEntityManagerSubscriber::MESSENGER_WORKER_MESSAGE_HANDLED]);
        self::assertSame(['onMessageFinished', -1024], $events[OwnEntityManagerSubscriber::MESSENGER_WORKER_MESSAGE_FAILED]);
        self::assertSame(['onWorkerStopped', -1024], $events[OwnEntityManagerSubscriber::MESSENGER_WORKER_STOPPED]);
        self::assertSame(['onConsoleCommand', 4096], $events[ConsoleEvents::COMMAND]);
        self::assertSame(['onConsoleTerminate', -1024], $events[ConsoleEvents::TERMINATE]);
    }

    #[Test]
    public function anUninitializedManagerIsNeverTouched(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method(self::anything());

        new OwnEntityManagerSubscriber([], [$registry], 'page_builder')->clearState();
    }

    #[Test]
    public function anOpenManagerIsClearedAndAClosedOneRecreatedByName(): void
    {
        $open = $this->createMock(EntityManagerInterface::class);
        $open->method('isOpen')->willReturn(true);
        $open->expects(self::once())->method('clear');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('resetManager');
        new OwnEntityManagerSubscriber([$open], [$registry], 'page_builder')->clearState();

        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects(self::never())->method('clear');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('resetManager')->with('page_builder');
        new OwnEntityManagerSubscriber([$closed], [$registry], 'page_builder')->clearState();
    }

    #[Test]
    public function clearsAtTheMainRequestStartAndAtTerminateOnly(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onRequest($this->request(HttpKernelInterface::SUB_REQUEST));
        self::assertSame(0, $this->clears);
        $subscriber->onRequest($this->request(HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(1, $this->clears);
        $subscriber->onFinishRequest($this->finish(HttpKernelInterface::SUB_REQUEST));
        $subscriber->onFinishRequest($this->finish(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onTerminate();
        self::assertSame(2, $this->clears);
    }

    #[Test]
    public function aNestedCommandDoesNotClearTheOuterCommand(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onConsoleCommand();
        self::assertSame(1, $this->clears);
        $subscriber->onConsoleCommand();
        $subscriber->onConsoleTerminate();
        self::assertSame(1, $this->clears, 'a nested command must not clear the outer command');
        $subscriber->onConsoleTerminate();
        self::assertSame(2, $this->clears);

        // A stray terminate never drives the depth below zero.
        $subscriber->onConsoleTerminate();
        self::assertSame(3, $this->clears);
        $subscriber->onConsoleCommand();
        self::assertSame(4, $this->clears);
    }

    #[Test]
    public function aCommandInsideAHandlerDoesNotClearTheMessage(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onMessageReceived();
        self::assertSame(1, $this->clears);
        $subscriber->onConsoleCommand();
        $subscriber->onConsoleTerminate();
        self::assertSame(1, $this->clears, 'a command run from a handler must not clear the message');
        $subscriber->onMessageFinished();
        self::assertSame(2, $this->clears);

        // A skipped message (no handled / failed event): the stopped worker ends the in-flight state.
        $subscriber->onMessageReceived();
        $subscriber->onWorkerStopped();
        self::assertSame(3, $this->clears);
        $subscriber->onConsoleCommand();
        self::assertSame(4, $this->clears);
    }

    #[Test]
    public function aHandlerInsideACommandDoesNotClearTheCommandWhenItFinishes(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onConsoleCommand();
        self::assertSame(1, $this->clears);
        // messenger:consume run in-process: each message is its own unit of work.
        $subscriber->onMessageReceived();
        self::assertSame(2, $this->clears);
        $subscriber->onConsoleCommand();
        $subscriber->onConsoleTerminate();
        self::assertSame(2, $this->clears, 'a command run from the handler must not clear the message');
        $subscriber->onMessageFinished();
        self::assertSame(3, $this->clears);
        $subscriber->onConsoleTerminate();
        self::assertSame(4, $this->clears);
    }

    #[Test]
    public function aRequestInsideACommandOrAMessageDoesNotClearIt(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onConsoleCommand();
        self::assertSame(1, $this->clears);
        $subscriber->onRequest($this->request(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onFinishRequest($this->finish(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onTerminate();
        self::assertSame(1, $this->clears, 'a request handled inside a command must not clear it');
        $subscriber->onConsoleTerminate();
        self::assertSame(2, $this->clears);

        $subscriber->onMessageReceived();
        self::assertSame(3, $this->clears);
        $subscriber->onRequest($this->request(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onFinishRequest($this->finish(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onTerminate();
        self::assertSame(3, $this->clears, 'a request handled inside a message must not clear it');
        $subscriber->onMessageFinished();
        self::assertSame(4, $this->clears);
    }

    #[Test]
    public function aCommandInsideAMainRequestDoesNotClearTheRequest(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onRequest($this->request(HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(1, $this->clears);
        $subscriber->onConsoleCommand();
        $subscriber->onConsoleTerminate();
        self::assertSame(1, $this->clears, 'a command run from a controller must not clear the request');
        $subscriber->onFinishRequest($this->finish(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onConsoleCommand();
        self::assertSame(2, $this->clears);
    }

    private function request(int $type): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }

    private function finish(int $type): FinishRequestEvent
    {
        return new FinishRequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }

    private function subscriber(): OwnEntityManagerSubscriber
    {
        $this->clears = 0;
        $manager      = $this->createStub(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturn(true);
        $manager->method('clear')->willReturnCallback(function (): void {
            ++$this->clears;
        });

        return new OwnEntityManagerSubscriber([$manager], [], 'page_builder');
    }
}
