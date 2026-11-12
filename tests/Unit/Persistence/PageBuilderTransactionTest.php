<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Nowo\PageBuilderKitBundle\Event\PageCreatedEvent;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderOutbox;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\PageBuilderKitBundle\Persistence\UnitOfWorkBoundaries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(PageBuilderTransaction::class)]
final class PageBuilderTransactionTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    private bool $transactionActive = false;

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
    public function bundleModeInsideAHostTransactionFollowsItsCommitOrRollback(): void
    {
        $outbox      = new PageBuilderOutbox();
        $driver      = new stdClass();
        $transaction = new PageBuilderTransaction($this->manager(), $this->dispatcher(), outbox: $outbox);

        // Host transaction rolled back: the event is dropped, also at the next boundary (LG-PBK-M1).
        $outbox->transactionStarted($driver);
        $this->transactionActive = true;
        $transaction->record(true, new PageCreatedEvent('rolled', 'back'));
        $transaction->commit();
        self::assertSame(1, $transaction->countPending());
        self::assertSame(0, $transaction->dispatchPending());
        $outbox->transactionEnded($driver, false);
        $this->transactionActive = false;
        $transaction->onBoundary();
        self::assertSame([], $this->dispatched);
        self::assertSame(0, $transaction->countPending());

        // Host transaction committed: dispatched once DBAL reports no open transaction.
        $outbox->transactionStarted($driver);
        $this->transactionActive = true;
        $transaction->record(true, $event = new PageCreatedEvent('k', 'u'));
        $outbox->transactionEnded($driver, true);
        self::assertSame(0, $transaction->dispatchPending(), 'DBAL still inside its commit');
        $this->transactionActive = false;
        self::assertSame(1, $transaction->dispatchPending());
        self::assertSame([$event], $this->dispatched);
    }

    #[Test]
    public function aFailedRequestKeepsTheEventsOfCommittedWritesAndDropsTheUnflushedOnes(): void
    {
        $outbox      = new PageBuilderOutbox();
        $driver      = new stdClass();
        $em          = $this->manager();
        $transaction = new PageBuilderTransaction($em, $this->dispatcher(), true, outbox: $outbox);

        // Host use case: write, flush inside its transaction, commit.
        $transaction->commit($committed = new PageCreatedEvent('committed', 'u'));
        $outbox->transactionStarted($driver);
        $this->transactionActive = true;
        $transaction->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
        self::assertSame(1, $transaction->countPending(), 'another manager flushed: still unflushed');
        $transaction->postFlush(new PostFlushEventArgs($em));
        $outbox->transactionEnded($driver, true);
        $this->transactionActive = false;

        // A later write never flushed, then the request fails and ends (kernel.terminate, LG-PBK-L1).
        $transaction->commit(new PageCreatedEvent('never', 'flushed'));
        $transaction->onBoundary();
        self::assertSame([$committed], $this->dispatched);
        self::assertSame(0, $transaction->countPending());
    }

    #[Test]
    public function aThrowingListenerIsLoggedAndTheOtherEventsAreStillDispatched(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof PageCreatedEvent && $event->pageKey === 'boom') {
                throw new RuntimeException('listener');
            }
            $this->dispatched[] = $event;

            return $event;
        });
        $transaction = new PageBuilderTransaction($this->manager(), $dispatcher, logger: $logger);

        $transaction->record(true, new PageCreatedEvent('boom', 'u'), $ok = new PageCreatedEvent('ok', 'u'));
        self::assertSame([$ok], $this->dispatched);
    }

    #[Test]
    public function hostModeNeverFlushesAndWaitsForTheHostFlushOfTheBundleManager(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
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
        $transaction->discardUnflushed();
        $transaction->commit(new PageCreatedEvent('never', 'flushed'));
        $transaction->onBoundary();

        self::assertSame([], $this->dispatched);
        self::assertSame(0, $transaction->countPending());
    }

    #[Test]
    public function resetKeepsNothing(): void
    {
        $this->transactionActive = true;
        $transaction             = new PageBuilderTransaction($this->manager());
        $transaction->commit(new PageCreatedEvent('k', 'u'));
        self::assertSame(1, $transaction->countPending());

        $transaction->reset();
        self::assertSame(0, $transaction->countPending());

        $this->transactionActive = false;
        $transaction->commit(new PageCreatedEvent('k', 'u'));
        self::assertSame(0, $transaction->countPending(), 'no dispatcher: dispatched to nobody');
    }

    #[Test]
    public function subscribesToEveryBoundary(): void
    {
        $events = PageBuilderTransaction::getSubscribedEvents();
        self::assertSame(['onHttpTerminate', 1024], $events[KernelEvents::TERMINATE]);
        self::assertArrayNotHasKey(KernelEvents::EXCEPTION, $events, 'kernel.terminate is the HTTP boundary');
        self::assertSame(['onConsoleBoundary', -1024], $events[UnitOfWorkBoundaries::CONSOLE_ERROR]);
        self::assertSame(['onConsoleBoundary', 1024], $events[UnitOfWorkBoundaries::CONSOLE_TERMINATE]);
        self::assertSame(['onMessageFinished', 1024], $events[UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED]);
        self::assertSame(['onMessageFinished', -1024], $events[UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_FAILED]);
        self::assertSame(['onHttpRequest', 4096], $events[KernelEvents::REQUEST]);
        self::assertCount(6, $events);
    }

    /**
     * 1.15.1: nested boundaries dispatch what was committed but never discard the outer unit's unflushed events.
     */
    #[Test]
    public function nestedBoundariesKeepTheOuterUnflushedEvents(): void
    {
        $boundaries  = new UnitOfWorkBoundaries();
        $transaction = new PageBuilderTransaction($this->manager(), $this->dispatcher(), true, boundaries: $boundaries);
        $events      = new BoundaryEvents($boundaries, $transaction);
        $outer       = new Command('app:outer');
        $nested      = new Command('app:nested');

        // Nested command (error and terminate), an unknown nested command, a request handled inside the command.
        $events->command($outer);
        $transaction->commit(new PageCreatedEvent('outer', 'command'));
        $events->command($nested);
        $events->error($nested);
        $events->terminate($nested);
        $events->error(null);
        $events->request();
        $events->exception();
        $events->finishRequest();
        $events->kernelTerminate();
        self::assertSame(1, $transaction->countPending(), 'nested boundaries keep the outer command\'s events');
        $events->error($outer);
        self::assertSame(0, $transaction->countPending(), 'the outermost command\'s failure discards them');
        $events->terminate($outer);

        // A command run from a handler, and a request handled inside a message.
        $events->message(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
        $transaction->commit(new PageCreatedEvent('outer', 'message'));
        $events->command($nested);
        $events->terminate($nested);
        $events->mainRequest();
        self::assertSame(1, $transaction->countPending(), 'a command or request inside the message keeps its events');
        $events->message(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED);
        self::assertSame(0, $transaction->countPending());

        // A command run from a controller; an exception (sub-request or main) is not a boundary (NL2-3, NL2-v N4).
        $events->request();
        $transaction->commit(new PageCreatedEvent('outer', 'request'));
        $events->command($nested);
        $events->terminate($nested);
        $events->exception(HttpKernelInterface::SUB_REQUEST);
        $events->exception();
        self::assertSame(1, $transaction->countPending(), 'a command or a failing (sub-)request keeps the request\'s events');
        $events->finishRequest();
        $events->kernelTerminate();
        self::assertSame(0, $transaction->countPending(), 'the end of the main request discards them');

        // An unknown command with nothing else running is the outermost unit (NL2-2).
        $transaction->commit(new PageCreatedEvent('top', 'level'));
        $events->error(null);
        self::assertSame(0, $transaction->countPending());
        self::assertSame([], $this->dispatched, 'never flushed: nothing dispatched');
    }

    /**
     * Safety net (1.15.1): a runtime that never called kernel.terminate; the next outermost main request discards the
     * leftover unflushed events, a request inside a command or a sub-request does not.
     */
    #[Test]
    public function anOutermostMainRequestDiscardsLeftoversOfAnUnterminatedRequest(): void
    {
        $boundaries  = new UnitOfWorkBoundaries();
        $transaction = new PageBuilderTransaction($this->manager(), $this->dispatcher(), true, boundaries: $boundaries);
        $events      = new BoundaryEvents($boundaries, $transaction);
        $command     = new Command('app:crawl');

        $events->request();
        $transaction->commit(new PageCreatedEvent('left', 'over'));
        $events->finishRequest();
        $events->request(HttpKernelInterface::SUB_REQUEST);
        $events->command($command);
        $events->request();
        self::assertSame(1, $transaction->countPending(), 'a sub-request or a request inside a command keeps them');
        $events->finishRequest();
        $events->terminate($command);
        self::assertSame(0, $transaction->countPending(), 'the outermost command ended');

        $transaction->commit(new PageCreatedEvent('left', 'over'));
        $events->request();
        self::assertSame(0, $transaction->countPending(), 'the next outermost main request discards them');
        self::assertSame([], $this->dispatched);
    }

    private function manager(bool $flushes = false): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturnCallback(fn (): bool => $this->transactionActive);
        if ($flushes) {
            $em = $this->createMock(EntityManagerInterface::class);
            $em->expects(self::once())->method('flush');
        } else {
            $em = $this->createStub(EntityManagerInterface::class);
        }
        $em->method('getConnection')->willReturn($connection);

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
