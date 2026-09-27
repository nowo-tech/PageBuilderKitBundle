<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget\Type;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\TextWidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextWidgetType::class)]
final class TextWidgetTypeTest extends TestCase
{
    #[Test]
    public function defaultPropsAndMetadata(): void
    {
        $type = new TextWidgetType();

        self::assertSame('text', $type->getType());
        self::assertSame(['html' => ''], $type->defaultProps());
        self::assertStringContainsString('text.html.twig', $type->getPublicTemplate());
    }

    #[Test]
    public function sanitizePropsUsesProtectionStrategy(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::Strip, null),
        );

        $result = (new TextWidgetType())->sanitizeProps(['html' => '<b>x</b>'], $protection);

        self::assertSame('x', $result['html']);
    }

    #[Test]
    public function sanitizePropsCoercesNonStringHtmlToEmpty(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        $result = (new TextWidgetType())->sanitizeProps(['html' => 99], $protection);

        self::assertSame('', $result['html']);
    }
}
