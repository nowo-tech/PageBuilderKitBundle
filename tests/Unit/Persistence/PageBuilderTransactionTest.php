<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Nowo\PageBuilderKitBundle\Event\PageCreatedEvent;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(PageBuilderTransaction::class)]
final class PageBuilderTransactionTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    private bool $transactionActive = false;

    private bool $open = true;

    #[Test]
    public function bundleModeFlushesThenDispatchesAfterCommit(): void
    {
        $em          = $this->manager(flushes: true);
        $transaction = new PageBuilderTransaction($em, $this->dispatcher());

        self::assertFalse($transaction->isHostControlled());
        $transaction->commit($event = new PageCreatedEvent('k', 'u'));

        self::assertSame([$event], $this->dispatched);
        self::assertSame(0, $transaction->countPending());
    }

    #[Test]
    public function bundleModeWaitsForAnOpenHostTransaction(): void
    {
        $this->transactionActive = true;
        $transaction             = new PageBuilderTransaction($this->manager(), $this->dispatcher());
        $transaction->commit(new PageCreatedEvent('k', 'u'));
        $transaction->commit();

        self::assertSame([], $this->dispatched);
        self::assertSame(1, $transaction->countPending());
        self::assertSame(0, $transaction->dispatchPending());

        $this->transactionActive = false;
        self::assertSame(1, $transaction->dispatchPending());
        self::assertCount(1, $this->dispatched);
    }

    #[Test]
    public function hostModeNeverFlushesAndWaitsForTheHostFlushOfTheBundleManager(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $em->method('isOpen')->willReturn(true);
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $em->method('getConnection')->willReturn($connection);
        $transaction = new PageBuilderTransaction($em, $this->dispatcher(), hostControlsTransaction: true);
        self::assertTrue($transaction->isHostControlled());

        $transaction->commit(new PageCreatedEvent('k', 'u'));
        self::assertSame(0, $transaction->dispatchPending(), 'not flushed yet');

        $transaction->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
        self::assertSame([], $this->dispatched, 'another manager flushed');

        $transaction->postFlush(new PostFlushEventArgs($em));
        self::assertCount(1, $this->dispatched);
    }

    #[Test]
    public function hostModeStillFlushesDuringTheBundleOwnAdminRequests(): void
    {
        $stack = new RequestStack();
        $em    = $this->manager(flushes: true);

        $transaction = new PageBuilderTransaction($em, $this->dispatcher(), true, requestStack: $stack);
        self::assertFalse($transaction->flushesItself(), 'no request (CLI / host use case)');

        $hostRequest = Request::create('/host');
        $hostRequest->attributes->set('_controller', 'App\\Controller\\PublishController::__invoke');
        $stack->push($hostRequest);
        self::assertFalse($transaction->flushesItself(), 'host controller: the host commits');
        $stack->pop();

        $adminRequest = Request::create('/admin/page-builder');
        $adminRequest->attributes->set('_controller', 'Nowo\\PageBuilderKitBundle\\Controller\\Admin\\PageDocumentApiController::publish');
        $stack->push($adminRequest);
        self::assertTrue($transaction->flushesItself());

        $transaction->commit(new PageCreatedEvent('k', 'u'));
        self::assertCount(1, $this->dispatched, 'flushed by the bundle UI: dispatched after its commit');
    }

    #[Test]
    public function boundaryDispatchesCommittedAndDiscardsTheRestWithAWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $transaction = new PageBuilderTransaction($this->manager(), $this->dispatcher(), true, $logger);

        $transaction->onBoundary();
        $transaction->discardPending();
        $transaction->commit(new PageCreatedEvent('never', 'flushed'));
        $transaction->onBoundary();

        self::assertSame([], $this->dispatched);
        self::assertSame(0, $transaction->countPending());
    }

    #[Test]
    public function closedManagerAndResetKeepNothing(): void
    {
        $this->open  = false;
        $transaction = new PageBuilderTransaction($this->manager());
        $transaction->commit(new PageCreatedEvent('k', 'u'));
        self::assertSame(0, $transaction->dispatchPending());
        self::assertSame(1, $transaction->countPending());

        $transaction->reset();
        self::assertSame(0, $transaction->countPending());

        $this->open = true;
        $transaction->commit(new PageCreatedEvent('k', 'u'));
        self::assertSame(0, $transaction->countPending(), 'no dispatcher: dispatched to nobody');
    }

    #[Test]
    public function subscribesToEveryBoundary(): void
    {
        $events = PageBuilderTransaction::getSubscribedEvents();
        self::assertSame(['onBoundary', 1024], $events[KernelEvents::TERMINATE]);
        self::assertSame(['discardPending', -1024], $events[KernelEvents::EXCEPTION]);
        self::assertCount(6, $events);
    }

    private function manager(bool $flushes = false): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturnCallback(fn (): bool => $this->transactionActive);
        $em = $flushes ? $this->createMock(EntityManagerInterface::class) : $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('isOpen')->willReturnCallback(fn (): bool => $this->open);
        if ($em instanceof MockObject) {
            $em->expects(self::once())->method('flush');
        }

        return $em;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            $this->dispatched[] = $event;

            return $event;
        });

        return $dispatcher;
    }
}
