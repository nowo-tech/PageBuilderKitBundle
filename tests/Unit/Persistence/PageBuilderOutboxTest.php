<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Nowo\PageBuilderKitBundle\Event\PageCreatedEvent;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderOutbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PageBuilderOutbox::class)]
final class PageBuilderOutboxTest extends TestCase
{
    #[Test]
    public function withoutATransactionEventsAreCommittedAtOnce(): void
    {
        $outbox = new PageBuilderOutbox();
        $outbox->hold();
        $outbox->hold($event = new PageCreatedEvent('a', 'u'));

        self::assertSame(1, $outbox->countCommitted());
        self::assertSame([$event], $outbox->takeCommitted());
        self::assertSame(0, $outbox->countCommitted());
    }

    #[Test]
    public function savepointsCommitIntoTheirParentOrDropTheirEvents(): void
    {
        $outbox = new PageBuilderOutbox();
        $driver = new stdClass();
        $outbox->savepointCreated($driver, 'IGNORED'); // no transaction: ignored

        $outbox->transactionStarted($driver);
        $outbox->hold($outer = new PageCreatedEvent('outer', 'u'));
        $outbox->savepointCreated($driver, 'DOCTRINE_2');
        $outbox->hold($released = new PageCreatedEvent('released', 'u'));
        $outbox->savepointReleased($driver, 'doctrine_2');
        $outbox->savepointCreated($driver, 'DOCTRINE_2');
        $outbox->hold(new PageCreatedEvent('rolled-back', 'u'));
        $outbox->savepointCreated($driver, 'DOCTRINE_3');
        $outbox->hold(new PageCreatedEvent('inner-of-rolled-back', 'u'));
        $outbox->savepointRolledBack($driver, 'DOCTRINE_2');
        $outbox->savepointRolledBack($driver, 'UNKNOWN');
        self::assertSame(2, $outbox->countHeld());

        $outbox->transactionEnded($driver, true);
        self::assertSame(0, $outbox->countHeld());
        self::assertSame([$outer, $released], $outbox->takeCommitted());
    }

    #[Test]
    public function aReusedSavepointNameClosesTheOpenOneFirst(): void
    {
        $outbox = new PageBuilderOutbox();
        $driver = new stdClass();
        $outbox->transactionStarted($driver);
        $outbox->savepointCreated($driver, 'SP');
        $outbox->hold($first = new PageCreatedEvent('first', 'u'));
        $outbox->savepointCreated($driver, 'SP');
        $outbox->hold(new PageCreatedEvent('second', 'u'));
        $outbox->savepointRolledBack($driver, 'SP');
        $outbox->transactionEnded($driver, true);

        self::assertSame([$first], $outbox->takeCommitted());
    }

    #[Test]
    public function rollbackAndResetDropEverything(): void
    {
        $outbox = new PageBuilderOutbox();
        $driver = new stdClass();
        $outbox->transactionStarted($driver);
        $outbox->hold(new PageCreatedEvent('a', 'u'));
        $outbox->transactionEnded($driver, false);
        self::assertSame([], $outbox->takeCommitted());

        $outbox->hold(new PageCreatedEvent('b', 'u'));
        $outbox->transactionStarted($driver);
        $outbox->hold(new PageCreatedEvent('c', 'u'));
        $outbox->reset();
        self::assertSame(0, $outbox->countCommitted() + $outbox->countHeld());
        $outbox->transactionEnded($driver, true);
        self::assertSame([], $outbox->takeCommitted());
    }
}
