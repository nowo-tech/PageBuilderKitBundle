<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\TemplateApplyType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(TemplateApplyType::class)]
final class TemplateApplyTypeTest extends TestCase
{
    #[Test]
    public function buildFormAndConfigureOptions(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $form = $factory->create(TemplateApplyType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'pageKey'              => 'landing',
            'title'                => 'Landing',
            'include_field_schema' => '1',
            'include_field_values' => '1',
            'include_seo'          => '1',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('landing', $form->get('pageKey')->getData());
        self::assertSame('Landing', $form->get('title')->getData());
        self::assertTrue($form->get('include_field_schema')->getData());
        self::assertTrue($form->get('include_field_values')->getData());
        self::assertTrue($form->get('include_seo')->getData());
        self::assertInstanceOf(TemplateApplyType::class, $form->getConfig()->getType()->getInnerType());
    }
}
