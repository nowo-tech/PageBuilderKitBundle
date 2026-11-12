<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Nowo\PageBuilderKitBundle\Persistence\UnitOfWorkBoundaries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelEvents;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * 1.15.1 (NL2): the unit-of-work tracker changes state before any other listener can throw, remembers each command's
 * verdict, never lets a stray terminate pop another command, and heals after a failed error listener.
 */
#[CoversClass(UnitOfWorkBoundaries::class)]
final class UnitOfWorkBoundariesTest extends TestCase
{
    #[Test]
    public function changesStateAtTheExtremePriorities(): void
    {
        $events = UnitOfWorkBoundaries::getSubscribedEvents();

        self::assertSame(['onRequest', PHP_INT_MAX], $events[KernelEvents::REQUEST]);
        self::assertSame(['onFinishRequest', PHP_INT_MAX], $events[KernelEvents::FINISH_REQUEST]);
        self::assertSame(['onCommand', PHP_INT_MAX], $events[UnitOfWorkBoundaries::CONSOLE_COMMAND]);
        self::assertSame([['onErrorStarted', PHP_INT_MAX], ['onErrorHandled', PHP_INT_MIN]], $events[UnitOfWorkBoundaries::CONSOLE_ERROR]);
        self::assertSame(['onTerminate', PHP_INT_MAX], $events[UnitOfWorkBoundaries::CONSOLE_TERMINATE]);
        self::assertSame(['onMessageFinished', PHP_INT_MAX], $events[UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_SKIPPED]);
        self::assertSame(['onWorkerStopped', PHP_INT_MAX], $events[UnitOfWorkBoundaries::MESSENGER_WORKER_STOPPED]);
        self::assertCount(10, $events);
    }

    #[Test]
    public function aThrowingTerminateListenerStillEndsTheCommand(): void
    {
        $events  = new BoundaryEvents(new UnitOfWorkBoundaries());
        $command = new Command('app:x');
        $events->dispatcher->addListener(UnitOfWorkBoundaries::CONSOLE_TERMINATE, static function (): never {
            throw new RuntimeException('terminate listener failed');
        });

        $events->command($command);
        try {
            $events->terminate($command);
            self::fail('the terminate listener throws');
        } catch (RuntimeException) {
        }

        self::assertSame(0, $events->boundaries->runningCommands());
        self::assertTrue($events->boundaries->isHttpOutermost());
    }

    #[Test]
    public function anOuterTerminateDropsNestedCommandsThatNeverTerminated(): void
    {
        $events = new BoundaryEvents(new UnitOfWorkBoundaries());
        $outer  = new Command('app:outer');

        $events->command($outer);
        $events->command(new Command('app:lost-1'));
        $events->command(new Command('app:lost-2'));
        self::assertSame(3, $events->boundaries->runningCommands());

        $terminate = new ConsoleTerminateEvent($outer, new ArrayInput([]), new NullOutput(), 0);
        $events->dispatcher->dispatch($terminate, UnitOfWorkBoundaries::CONSOLE_TERMINATE);
        self::assertSame(0, $events->boundaries->runningCommands());
        self::assertTrue($events->boundaries->isOutermost($terminate));
    }

    #[Test]
    public function aFailedErrorListenerHealsAtTheNextUnitOnly(): void
    {
        $boundaries = new UnitOfWorkBoundaries();
        $events     = new BoundaryEvents($boundaries);
        $outer      = new Command('app:outer');
        $failing    = new Command('app:failing');

        // A handled error (the listeners finish) keeps the command until its terminate.
        $events->command($outer);
        $events->error($outer);
        $events->error(new Command('app:unknown-to-the-tracker'));
        $events->message(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
        self::assertSame(1, $boundaries->runningCommands());
        $events->message(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED);

        // The nested command's error listener throws: no terminate; the next command heals, the outer one stays.
        $events->dispatcher->addListener(UnitOfWorkBoundaries::CONSOLE_ERROR, static function (): never {
            throw new RuntimeException('error listener failed');
        });
        $events->command($failing);
        try {
            $events->error($failing);
        } catch (RuntimeException) {
        }
        self::assertSame(2, $boundaries->runningCommands());
        $events->command(new Command('app:next'));
        self::assertSame(2, $boundaries->runningCommands(), 'failing entry dropped, the new one pushed');

        // The same at a stray terminate and when the worker stops.
        $events->command($failing);
        try {
            $events->error($failing);
        } catch (RuntimeException) {
        }
        $events->terminate(new Command('app:stray'));
        self::assertSame(2, $boundaries->runningCommands());
        $events->command($failing);
        try {
            $events->error($failing);
        } catch (RuntimeException) {
        }
        $events->message(UnitOfWorkBoundaries::MESSENGER_WORKER_STOPPED);
        self::assertSame(2, $boundaries->runningCommands());
    }

    #[Test]
    public function verdictsAreKeptPerEventAndUnseenEventsUseTheCurrentState(): void
    {
        $boundaries = new UnitOfWorkBoundaries();
        $events     = new BoundaryEvents($boundaries);

        self::assertTrue($boundaries->isOutermost(new stdClass()));
        $start = new ConsoleCommandEvent(null, new ArrayInput([]), new NullOutput());
        $events->dispatcher->dispatch($start, UnitOfWorkBoundaries::CONSOLE_COMMAND);
        self::assertTrue($boundaries->isOutermost($start), 'the verdict of the start, whatever runs now');
        self::assertFalse($boundaries->isOutermost(new stdClass()));
        self::assertFalse($boundaries->isHttpOutermost());

        $events->request();
        $events->finishRequest();
        self::assertSame(1, $boundaries->runningCommands());
    }
}
