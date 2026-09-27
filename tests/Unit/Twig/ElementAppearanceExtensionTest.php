<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Twig;

use Nowo\PageBuilderKitBundle\Service\ElementAppearanceNormalizer;
use Nowo\PageBuilderKitBundle\Twig\ElementAppearanceExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElementAppearanceExtension::class)]
final class ElementAppearanceExtensionTest extends TestCase
{
    #[Test]
    public function elementAttrsAndAttrMapRenderSafeHtml(): void
    {
        $extension = new ElementAppearanceExtension(new ElementAppearanceNormalizer());

        $map = $extension->elementAttrMap([
            'cssId'      => 'hero',
            'cssClasses' => 'lead',
            'style'      => ['marginTop' => '4px'],
        ], ['extra']);

        self::assertSame('hero', $map['id']);
        self::assertStringContainsString('extra', $map['class']);

        $html = $extension->elementAttrs([
            'cssId' => 'box',
            'style' => ['paddingTop' => '2px'],
        ]);

        self::assertStringContainsString('id="box"', $html);
        self::assertStringContainsString('padding-top', $html);
        self::assertCount(2, $extension->getFunctions());
    }

    #[Test]
    public function elementAttrMapTreatsNonArrayAppearanceAsEmpty(): void
    {
        $extension = new ElementAppearanceExtension(new ElementAppearanceNormalizer());

        $map = $extension->elementAttrMap(null);

        self::assertArrayHasKey('class', $map);
        self::assertStringContainsString('pbk-element', $extension->elementAttrs(null));
    }
}
