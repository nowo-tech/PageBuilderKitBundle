<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\TemplateImportType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(TemplateImportType::class)]
final class TemplateImportTypeTest extends TestCase
{
    #[Test]
    public function buildFormAndConfigureOptions(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $payload = '{"formatVersion":1,"kind":"page_builder_template","templateKey":"x"}';
        $form    = $factory->create(TemplateImportType::class, null, ['csrf_protection' => false]);
        $form->submit(['payload' => $payload]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame($payload, $form->get('payload')->getData());
        self::assertInstanceOf(TemplateImportType::class, $form->getConfig()->getType()->getInnerType());
    }
}
