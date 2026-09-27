<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Service\PageSeoBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageSeoBuilder::class)]
final class PageSeoBuilderTest extends TestCase
{
    #[Test]
    public function fallsBackToTitleAndConfigDefaults(): void
    {
        $builder = new PageSeoBuilder('Nowo', 'https://cdn.example/og.png', 'https://example.com', 'index,follow');
        $seo     = $builder->build(null, 'home', 'en', 'Home');

        self::assertSame('Home', $seo['title']);
        self::assertNull($seo['description']);
        self::assertSame('https://example.com/p/home', $seo['canonical']);
        self::assertSame('index,follow', $seo['robots']);
        self::assertSame('Home', $seo['og']['title']);
        self::assertSame('https://cdn.example/og.png', $seo['og']['image']);
        self::assertSame('Nowo', $seo['og']['site_name']);
        self::assertSame('en', $seo['og']['locale']);
    }

    #[Test]
    public function prefersTranslationFields(): void
    {
        $translation = (new BuilderPageTranslation())
            ->setLocale('es')
            ->setTitle('Inicio')
            ->setSlug('inicio')
            ->setMetaTitle('SEO Inicio')
            ->setMetaDescription('Descripción corta')
            ->setOgTitle('OG Inicio')
            ->setOgDescription('OG desc')
            ->setOgImage('https://cdn.example/custom.png')
            ->setCanonicalUrl('https://example.com/es/inicio')
            ->setRobots('noindex');

        $seo = (new PageSeoBuilder('Nowo', 'https://cdn.example/og.png', 'https://example.com'))
            ->build($translation, 'home', 'es', 'Inicio');

        self::assertSame('SEO Inicio', $seo['title']);
        self::assertSame('Descripción corta', $seo['description']);
        self::assertSame('https://example.com/es/inicio', $seo['canonical']);
        self::assertSame('noindex', $seo['robots']);
        self::assertSame('OG Inicio', $seo['og']['title']);
        self::assertSame('OG desc', $seo['og']['description']);
        self::assertSame('https://cdn.example/custom.png', $seo['og']['image']);
    }

    #[Test]
    public function canonicalIsNullWhenBaseUrlMissing(): void
    {
        $seo = (new PageSeoBuilder('', '', ''))->build(null, 'home', 'en', 'Home');

        self::assertNull($seo['canonical']);
    }
}
