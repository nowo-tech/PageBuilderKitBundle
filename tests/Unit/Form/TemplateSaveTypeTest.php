<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\TemplateSaveType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(TemplateSaveType::class)]
final class TemplateSaveTypeTest extends TestCase
{
    #[Test]
    public function buildFormAndConfigureOptions(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $form = $factory->create(TemplateSaveType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'templateKey' => 'hero-tpl',
            'label'       => 'Hero',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('hero-tpl', $form->get('templateKey')->getData());
        self::assertSame('Hero', $form->get('label')->getData());
        self::assertInstanceOf(TemplateSaveType::class, $form->getConfig()->getType()->getInnerType());
    }
}
