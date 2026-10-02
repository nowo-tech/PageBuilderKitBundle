<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Form\ContentSchemaAddType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(ContentSchemaAddType::class)]
final class ContentSchemaAddTypeTest extends TestCase
{
    #[Test]
    public function buildFormAndConfigureOptions(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $form = $factory->create(ContentSchemaAddType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'schema_action' => 'add',
            'key'           => 'hero_title',
            'type'          => ContentFieldType::String->value,
            'label'         => 'Hero',
            'options'       => 'a,b',
            'subfields'     => 'question:string',
            'min'           => '1',
            'max'           => '5',
            'required'      => '1',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('hero_title', $form->get('key')->getData());
        self::assertSame(ContentFieldType::String->value, $form->get('type')->getData());
        self::assertTrue($form->get('required')->getData());
        self::assertSame('', $form->getConfig()->getType()->getInnerType()->getBlockPrefix());
        self::assertInstanceOf(ContentSchemaAddType::class, $form->getConfig()->getType()->getInnerType());
    }
}
