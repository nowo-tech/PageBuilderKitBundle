<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shell form for the public inline-edit dialog (body filled by JS).
 *
 * @extends AbstractType<null>
 */
#[FormKitConfig('page_builder_kit')]
final class InlineFieldModalType extends AbstractType
{
    use FormOptionsTrait;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'pbk_inline_modal';
    }
}
