<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Enum;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageBuilderCapability::class)]
final class PageBuilderCapabilityTest extends TestCase
{
    #[Test]
    public function allReturnsFourCapabilities(): void
    {
        $all = PageBuilderCapability::all();

        self::assertSame([
            PageBuilderCapability::Layout,
            PageBuilderCapability::Content,
            PageBuilderCapability::Publish,
            PageBuilderCapability::Templates,
        ], $all);
        self::assertSame('layout', PageBuilderCapability::Layout->value);
        self::assertSame('content', PageBuilderCapability::Content->value);
        self::assertSame('publish', PageBuilderCapability::Publish->value);
        self::assertSame('templates', PageBuilderCapability::Templates->value);
    }
}
