<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Enum;

use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageStatus::class)]
final class PageStatusTest extends TestCase
{
    #[Test]
    public function draftAndPublishedValues(): void
    {
        self::assertSame('draft', PageStatus::Draft->value);
        self::assertSame('published', PageStatus::Published->value);
    }

    #[Test]
    public function fromStringRoundTrip(): void
    {
        self::assertSame(PageStatus::Draft, PageStatus::from('draft'));
        self::assertSame(PageStatus::Published, PageStatus::from('published'));
    }
}
