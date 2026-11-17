<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Html;

use Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * REQ-TEST-003: the PNG -> WebP picture upgrade rules of the public HTML normalizer.
 */
final class PublicHtmlNormalizerWebpUpgradeTest extends TestCase
{
    public function testAnImgIsWrappedInAPictureWithTheWebpSource(): void
    {
        $normalizer = new PublicHtmlNormalizer('skel', [['png' => '/a.png', 'webp' => '/a.webp']]);

        $html = $normalizer->normalize('<p><img class="x" src="/a.png" alt="A"></p>');

        self::assertSame('<p><picture><source srcset="/a.webp" type="image/webp"><img class="x" src="/a.png" alt="A"></picture></p>', $html);
    }

    public function testTheClassFilterRestrictsTheUpgrade(): void
    {
        $normalizer = new PublicHtmlNormalizer('skel', [['png' => '/a.png', 'webp' => '/a.webp', 'classContains' => 'hero']]);

        $upgraded  = $normalizer->normalize('<img class="hero big" src="/a.png">');
        $untouched = $normalizer->normalize('<img class="other" src="/a.png">');

        self::assertStringContainsString('<picture><source srcset="/a.webp"', $upgraded);
        self::assertStringNotContainsString('<picture>', $untouched);
    }

    public function testIncompleteOrAlreadyUpgradedRulesAreSkipped(): void
    {
        $normalizer = new PublicHtmlNormalizer('skel', [
            ['png' => '', 'webp' => '/a.webp'],
            ['png' => '/a.png', 'webp' => ''],
            ['png' => '/missing.png', 'webp' => '/missing.webp'],
            ['png' => '/a.png', 'webp' => '/a.webp'],
        ]);

        $html = '<picture><source srcset="/a.webp" type="image/webp"><img src="/a.png"></picture>';

        self::assertSame($html, $normalizer->normalize($html));
        self::assertSame('', $normalizer->normalize(''));
    }

    public function testAnEmptyClassFilterBehavesLikeNoFilter(): void
    {
        $normalizer = new PublicHtmlNormalizer('skel', [['png' => '/a.png', 'webp' => '/a.webp', 'classContains' => '']]);

        self::assertStringContainsString('<picture>', $normalizer->normalize('<img src="/a.png">'));
    }
}
