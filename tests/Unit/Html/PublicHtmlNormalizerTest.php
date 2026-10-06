<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Html;

use Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer;
use PHPUnit\Framework\TestCase;

final class PublicHtmlNormalizerTest extends TestCase
{
    public function testStripsSourceClosingAroundHeroImg(): void
    {
        $dirty = <<<'HTML'
<picture>
      <source srcset="/media/hero.webp" type="image/webp"><img class="hero__img" src="/media/hero.png" alt="Hero" width="395" height="631"></source></picture>
HTML;
        $clean = (new PublicHtmlNormalizer())->normalize($dirty);

        self::assertStringNotContainsString('</source>', $clean);
        self::assertStringContainsString('<source srcset="/media/hero.webp" type="image/webp">', $clean);
        self::assertStringContainsString('<img class="hero__img"', $clean);
        self::assertStringContainsString('</picture>', $clean);
    }

    public function testAddsPlaceholderSrcToSkeletonImg(): void
    {
        $dirty = '<img class="site-skeleton__img" data-src="/media/a.jpg" alt="A" width="800" height="560">';
        $clean = (new PublicHtmlNormalizer())->normalize($dirty);

        self::assertStringContainsString('src="data:image/gif;base64,', $clean);
        self::assertStringContainsString('data-src="/media/a.jpg"', $clean);
    }

    public function testDoesNotDuplicateSrcOnSkeletonImg(): void
    {
        $ok = '<img class="site-skeleton__img" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="/media/a.jpg" alt="A">';
        self::assertSame($ok, (new PublicHtmlNormalizer())->normalize($ok));
    }

    public function testOptionalWebpPictureUpgrade(): void
    {
        $dirty = '<img class="sign-day" src="/media/sign.png" alt="Sign" width="180" height="48">';
        $clean = (new PublicHtmlNormalizer(webpPictureUpgrades: [
            ['png' => '/media/sign.png', 'webp' => '/media/sign.webp', 'classContains' => 'sign-day'],
        ]))->normalize($dirty);

        self::assertStringContainsString('<picture>', $clean);
        self::assertStringContainsString('srcset="/media/sign.webp"', $clean);
        self::assertStringContainsString('src="/media/sign.png"', $clean);
        self::assertStringNotContainsString('</source>', $clean);
    }
}
