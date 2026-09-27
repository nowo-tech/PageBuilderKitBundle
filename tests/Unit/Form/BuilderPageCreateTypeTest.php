<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\BuilderPageCreateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(BuilderPageCreateType::class)]
final class BuilderPageCreateTypeTest extends TestCase
{
    #[Test]
    public function buildFormContainsExpectedFields(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $form = $factory->create(BuilderPageCreateType::class);
        $form->submit([
            'pageKey' => 'landing-page',
            'title'   => 'Landing',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('landing-page', $form->get('pageKey')->getData());
        self::assertSame('Landing', $form->get('title')->getData());
    }
}
