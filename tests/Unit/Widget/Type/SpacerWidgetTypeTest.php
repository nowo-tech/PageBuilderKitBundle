<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget\Type;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\SpacerWidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpacerWidgetType::class)]
final class SpacerWidgetTypeTest extends TestCase
{
    #[Test]
    public function defaultPropsAndMetadata(): void
    {
        $type = new SpacerWidgetType();

        self::assertSame('spacer', $type->getType());
        self::assertSame(['height' => '1rem'], $type->defaultProps());
        self::assertStringContainsString('spacer.html.twig', $type->getPublicTemplate());
    }

    #[Test]
    public function sanitizePropsKeepsValidHeight(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        $result = (new SpacerWidgetType())->sanitizeProps(['height' => '2rem'], $protection);

        self::assertSame('2rem', $result['height']);
    }

    #[Test]
    public function sanitizePropsFallsBackWhenHeightMissing(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        $result = (new SpacerWidgetType())->sanitizeProps(['height' => ''], $protection);

        self::assertSame('1rem', $result['height']);
    }
}
