<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetPropsMerger::class)]
final class WidgetPropsMergerTest extends TestCase
{
    private WidgetPropsMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new WidgetPropsMerger();
    }

    #[Test]
    public function sameLocaleReturnsLocalePropsOnly(): void
    {
        $localeProps = ['w1' => ['text' => 'A']];

        self::assertSame(
            $localeProps,
            $this->merger->mergePropsWithFallbackLocale($localeProps, ['w1' => ['text' => 'B']], 'es', 'es'),
        );
    }

    #[Test]
    public function mergesFallbackWithLocaleOverrides(): void
    {
        $result = $this->merger->mergePropsWithFallbackLocale(
            [
                'w1' => ['text' => 'locale'],
                'w2' => ['text' => 'only-locale'],
            ],
            [
                'w1' => ['text' => 'fallback', 'tag' => 'h2'],
            ],
            'en',
            'es',
        );

        self::assertSame('locale', $result['w1']['text']);
        self::assertSame('h2', $result['w1']['tag']);
        self::assertSame('only-locale', $result['w2']['text']);
    }

    #[Test]
    public function skipsInvalidWidgetKeys(): void
    {
        /** @var array<string, mixed> $localeProps */
        $localeProps = [
            'w1' => 'not-array',
        ];

        $result = $this->merger->mergePropsWithFallbackLocale(
            $localeProps,
            [],
            'en',
            'es',
        );

        self::assertSame([], $result);
    }
}
