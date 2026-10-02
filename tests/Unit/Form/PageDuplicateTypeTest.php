<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\PageDuplicateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(PageDuplicateType::class)]
final class PageDuplicateTypeTest extends TestCase
{
    #[Test]
    public function buildFormAndConfigureOptions(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $form = $factory->create(PageDuplicateType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'pageKey'   => 'landing-page',
            '_redirect' => '/pages',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('landing-page', $form->get('pageKey')->getData());
        self::assertSame('/pages', $form->get('_redirect')->getData());
        self::assertInstanceOf(PageDuplicateType::class, $form->getConfig()->getType()->getInnerType());
    }
}
