<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\ElementAppearanceNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElementAppearanceNormalizer::class)]
final class ElementAppearanceNormalizerTest extends TestCase
{
    private ElementAppearanceNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ElementAppearanceNormalizer();
    }

    #[Test]
    public function normalizesStyleClassesIdAndAttributes(): void
    {
        $result = $this->normalizer->normalize([
            'cssId'      => 'hero-block',
            'cssClasses' => 'foo bar invalid!class',
            'style'      => [
                'marginTop'       => '16px',
                'backgroundColor' => '#fff',
                'unknownProp'     => 'x',
                'color'           => 'expression(alert(1))',
            ],
            'attributes' => [
                ['name' => 'data-aos', 'value' => 'fade-up'],
                ['name' => 'onclick', 'value' => 'alert(1)'],
                ['name' => 'style', 'value' => 'color:red'],
            ],
            'width' => 6,
        ]);

        self::assertSame('hero-block', $result['cssId']);
        self::assertSame('foo bar', $result['cssClasses']);
        self::assertSame('16px', $result['style']['marginTop']);
        self::assertSame('#fff', $result['style']['backgroundColor']);
        self::assertArrayNotHasKey('color', $result['style']);
        self::assertSame([['name' => 'data-aos', 'value' => 'fade-up']], $result['attributes']);
        self::assertSame(6, $result['width']);
    }

    #[Test]
    public function buildsInlineCssAndHtmlAttributes(): void
    {
        $appearance = $this->normalizer->normalize([
            'cssId'      => 'box1',
            'cssClasses' => 'card',
            'style'      => ['paddingTop' => '8px', 'textAlign' => 'center'],
            'attributes' => [['name' => 'aria-label', 'value' => 'Hero']],
        ]);

        self::assertSame('padding-top:8px;text-align:center', $this->normalizer->toInlineCss($appearance['style']));

        $attrs = $this->normalizer->toHtmlAttributes($appearance);
        self::assertSame('box1', $attrs['id']);
        self::assertStringContainsString('pbk-element', $attrs['class']);
        self::assertStringContainsString('card', $attrs['class']);
        self::assertSame('padding-top:8px;text-align:center', $attrs['style']);
        self::assertSame('Hero', $attrs['aria-label']);
    }

    #[Test]
    public function normalizesResponsiveBucketsAndRejectsUnsafeValues(): void
    {
        $result = $this->normalizer->normalize([
            'css_id'      => 'bad id',
            'css_classes' => 'ok',
            'tablet'      => ['style' => ['marginTop' => '4px', 'color' => 'javascript:alert(1)']],
            'mobile'      => ['paddingTop' => '2px'],
            'attributes'  => [
                ['key' => 'role', 'value' => 'banner'],
                ['name' => 'onclick', 'value' => 'x'],
            ],
        ]);

        self::assertArrayNotHasKey('cssId', $result);
        self::assertSame('ok', $result['cssClasses']);
        self::assertSame('4px', $result['tablet']['style']['marginTop']);
        self::assertSame('2px', $result['mobile']['style']['paddingTop']);
        self::assertSame([['name' => 'role', 'value' => 'banner']], $result['attributes']);
    }

    #[Test]
    public function toHtmlAttributesSkipsConflictingCustomAttributes(): void
    {
        $appearance = $this->normalizer->normalize([
            'cssId'      => 'main',
            'cssClasses' => 'x',
            'style'      => ['marginTop' => '1px'],
            'attributes' => [
                ['name' => 'id', 'value' => 'override'],
                ['name' => 'class', 'value' => 'override'],
                ['name' => 'style', 'value' => 'color:red'],
                ['name' => 'data-test', 'value' => 'ok'],
            ],
        ]);

        $attrs = $this->normalizer->toHtmlAttributes($appearance);
        self::assertSame('main', $attrs['id']);
        self::assertStringContainsString('pbk-element', $attrs['class']);
        self::assertSame('margin-top:1px', $attrs['style']);
        self::assertSame('ok', $attrs['data-test']);
    }
}
