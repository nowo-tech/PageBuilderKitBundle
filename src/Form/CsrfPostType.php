<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-only POST (publish / unpublish / delete / restore).
 *
 * @extends AbstractType<array{_redirect?: string|null}>
 */
#[FormKitConfig('page_builder_kit')]
final class CsrfPostType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, '_redirect', HiddenType::class, [
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'page_builder_document',
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
