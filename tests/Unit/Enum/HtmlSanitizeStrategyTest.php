<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Enum;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlSanitizeStrategy::class)]
final class HtmlSanitizeStrategyTest extends TestCase
{
    #[Test]
    public function valuesReturnsAllCaseValues(): void
    {
        self::assertSame(
            ['none', 'strip', 'allowlist', 'service'],
            HtmlSanitizeStrategy::values(),
        );
    }

    #[Test]
    public function casesMatchKnownStrategies(): void
    {
        self::assertSame('none', HtmlSanitizeStrategy::None->value);
        self::assertSame('strip', HtmlSanitizeStrategy::Strip->value);
        self::assertSame('allowlist', HtmlSanitizeStrategy::Allowlist->value);
        self::assertSame('service', HtmlSanitizeStrategy::Service->value);
    }
}
