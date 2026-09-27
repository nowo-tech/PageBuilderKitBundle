<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget\Type;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\ImageWidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageWidgetType::class)]
final class ImageWidgetTypeTest extends TestCase
{
    #[Test]
    public function defaultPropsAndMetadata(): void
    {
        $type = new ImageWidgetType();

        self::assertSame('image', $type->getType());
        self::assertSame(['src' => '', 'alt' => ''], $type->defaultProps());
        self::assertStringContainsString('image.html.twig', $type->getPublicTemplate());
    }

    #[Test]
    public function sanitizePropsTrimsStrings(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        $result = (new ImageWidgetType())->sanitizeProps(
            ['src' => ' /img.png ', 'alt' => ' Alt '],
            $protection,
        );

        self::assertSame('/img.png', $result['src']);
        self::assertSame('Alt', $result['alt']);
    }
}
