<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\CsrfPostType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(CsrfPostType::class)]
final class CsrfPostTypeTest extends TestCase
{
    #[Test]
    public function buildFormAndConfigureOptions(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $form = $factory->create(CsrfPostType::class, null, ['csrf_protection' => false]);
        $form->submit(['_redirect' => '/admin']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->has('_redirect'));
        self::assertSame('/admin', $form->get('_redirect')->getData());
        self::assertInstanceOf(CsrfPostType::class, $form->getConfig()->getType()->getInnerType());
    }
}
