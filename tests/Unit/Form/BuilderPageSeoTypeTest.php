<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Form\BuilderPageSeoType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(BuilderPageSeoType::class)]
final class BuilderPageSeoTypeTest extends TestCase
{
    #[Test]
    public function bindsTranslationSeoFields(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $translation = (new BuilderPageTranslation())
            ->setLocale('es')
            ->setTitle('T')
            ->setSlug('t')
            ->setPage(new BuilderPage());

        $form = $factory->create(BuilderPageSeoType::class, $translation);
        $form->submit([
            'title'           => 'New title',
            'slug'            => 'new-slug',
            'metaTitle'       => 'Meta',
            'metaDescription' => 'Desc',
            'ogTitle'         => 'OG',
            'ogDescription'   => 'OGD',
            'ogImage'         => 'https://cdn/img.png',
            'canonicalUrl'    => 'https://example.com/p',
            'robots'          => 'noindex',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('New title', $translation->getTitle());
        self::assertSame('new-slug', $translation->getSlug());
        self::assertSame('Meta', $translation->getMetaTitle());
        self::assertSame('Desc', $translation->getMetaDescription());
        self::assertSame('OG', $translation->getOgTitle());
        self::assertSame('OGD', $translation->getOgDescription());
        self::assertSame('https://cdn/img.png', $translation->getOgImage());
        self::assertSame('https://example.com/p', $translation->getCanonicalUrl());
        self::assertSame('noindex', $translation->getRobots());
    }
}
