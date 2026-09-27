<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Entity;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderPageTranslation::class)]
final class BuilderPageTranslationTest extends TestCase
{
    #[Test]
    public function gettersAndSetters(): void
    {
        $page        = new BuilderPage();
        $translation = (new BuilderPageTranslation())
            ->setLocale('es')
            ->setTitle('Title')
            ->setSlug('slug')
            ->setMetaTitle('Meta')
            ->setMetaDescription('Desc')
            ->setOgTitle('OG')
            ->setOgDescription('OGD')
            ->setOgImage('https://cdn/img.png')
            ->setCanonicalUrl('https://example.com/p')
            ->setRobots('index,follow')
            ->setPage($page);

        self::assertNull($translation->getId());
        self::assertSame('es', $translation->getLocale());
        self::assertSame('Title', $translation->getTitle());
        self::assertSame('slug', $translation->getSlug());
        self::assertSame('Meta', $translation->getMetaTitle());
        self::assertSame('Desc', $translation->getMetaDescription());
        self::assertSame('OG', $translation->getOgTitle());
        self::assertSame('OGD', $translation->getOgDescription());
        self::assertSame('https://cdn/img.png', $translation->getOgImage());
        self::assertSame('https://example.com/p', $translation->getCanonicalUrl());
        self::assertSame('index,follow', $translation->getRobots());
        self::assertSame($page, $translation->getPage());
    }
}
