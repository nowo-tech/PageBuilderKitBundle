<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\ContentSchemaRemoveType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(ContentSchemaRemoveType::class)]
final class ContentSchemaRemoveTypeTest extends TestCase
{
    #[Test]
    public function buildFormAndConfigureOptions(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $form = $factory->create(ContentSchemaRemoveType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'schema_action' => 'remove',
            'key'           => 'hero_title',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('remove', $form->get('schema_action')->getData());
        self::assertSame('hero_title', $form->get('key')->getData());
        self::assertSame('', $form->getConfig()->getType()->getInnerType()->getBlockPrefix());
        self::assertInstanceOf(ContentSchemaRemoveType::class, $form->getConfig()->getType()->getInnerType());
    }
}
