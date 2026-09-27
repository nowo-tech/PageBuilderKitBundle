<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget\Type;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\ContainerWidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContainerWidgetType::class)]
final class ContainerWidgetTypeTest extends TestCase
{
    #[Test]
    public function metadataAndChildSupport(): void
    {
        $type = new ContainerWidgetType();

        self::assertSame('container', $type->getType());
        self::assertSame('widget.container.label', $type->getLabelKey());
        self::assertTrue($type->allowsChildren());
        self::assertSame(['tag' => 'div'], $type->defaultProps());
        self::assertStringContainsString('container.html.twig', $type->getPublicTemplate());
    }

    #[Test]
    public function sanitizePropsNormalizesTag(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        $props = (new ContainerWidgetType())->sanitizeProps(['tag' => 'MAIN'], $protection);
        self::assertSame('main', $props['tag']);

        $fallback = (new ContainerWidgetType())->sanitizeProps(['tag' => 'script'], $protection);
        self::assertSame('div', $fallback['tag']);
    }
}
