<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\Html\AllowlistPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Security\Html\NullPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Security\Html\PageBuilderHtmlSanitizerInterface;
use Nowo\PageBuilderKitBundle\Security\Html\StripPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageBuilderProtection::class)]
final class PageBuilderProtectionTest extends TestCase
{
    #[Test]
    public function noneStrategyUsesNullSanitizer(): void
    {
        $protection = $this->protection(HtmlSanitizeStrategy::None);

        self::assertInstanceOf(NullPageBuilderHtmlSanitizer::class, $protection->htmlSanitizer());
        self::assertSame('<b>x</b>', $protection->htmlSanitizer()->sanitize('<b>x</b>'));
    }

    #[Test]
    public function stripStrategyUsesStripSanitizer(): void
    {
        $protection = $this->protection(HtmlSanitizeStrategy::Strip);

        self::assertInstanceOf(StripPageBuilderHtmlSanitizer::class, $protection->htmlSanitizer());
        self::assertSame('x', $protection->htmlSanitizer()->sanitize('<b>x</b>'));
    }

    #[Test]
    public function allowlistStrategyUsesAllowlistSanitizer(): void
    {
        $protection = $this->protection(HtmlSanitizeStrategy::Allowlist);

        self::assertInstanceOf(AllowlistPageBuilderHtmlSanitizer::class, $protection->htmlSanitizer());
    }

    #[Test]
    public function serviceStrategyUsesCustomSanitizerWhenProvided(): void
    {
        $custom = new class implements PageBuilderHtmlSanitizerInterface {
            public function sanitize(string $html): string
            {
                return 'custom:' . $html;
            }
        };

        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::Service, 'app.custom_sanitizer'),
            $custom,
        );

        self::assertSame('custom:<i>a</i>', $protection->htmlSanitizer()->sanitize('<i>a</i>'));
    }

    #[Test]
    public function serviceStrategyFallsBackToNullSanitizerWithoutCustom(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::Service, null),
        );

        self::assertInstanceOf(NullPageBuilderHtmlSanitizer::class, $protection->htmlSanitizer());
    }

    private function protection(HtmlSanitizeStrategy $strategy): PageBuilderProtection
    {
        return new PageBuilderProtection(
            new PageBuilderProtectionConfig($strategy, null),
        );
    }
}
