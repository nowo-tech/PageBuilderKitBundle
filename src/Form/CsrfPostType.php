<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-only POST (publish / unpublish / delete / restore).
 *
 * @extends AbstractType<array{_redirect?: string|null}>
 */
final class CsrfPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('_redirect', HiddenType::class, [
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
