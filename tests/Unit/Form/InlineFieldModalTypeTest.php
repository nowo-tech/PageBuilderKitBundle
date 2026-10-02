<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\InlineFieldModalType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Forms;

#[CoversClass(InlineFieldModalType::class)]
final class InlineFieldModalTypeTest extends TestCase
{
    #[Test]
    public function configuresCsrfOffAndBlockPrefix(): void
    {
        $type = new InlineFieldModalType();
        self::assertSame('pbk_inline_modal', $type->getBlockPrefix());
        self::assertSame(FormType::class, $type->getParent());

        $form = Forms::createFormFactoryBuilder()
            ->getFormFactory()
            ->create(InlineFieldModalType::class);

        self::assertFalse($form->getConfig()->getOption('csrf_protection'));
    }
}
