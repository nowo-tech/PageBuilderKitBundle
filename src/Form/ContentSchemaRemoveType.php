<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Remove a content-field schema row (CSRF id: page_builder_content).
 *
 * @extends AbstractType<array{schema_action: string, key: string}>
 */
#[FormKitConfig('page_builder_kit')]
final class ContentSchemaRemoveType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'schema_action', HiddenType::class, [
            'data' => 'remove',
        ]);
        $this->addWithDefaults($builder, 'key', HiddenType::class, [
            'constraints' => [new Assert\NotBlank()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection'    => true,
            'csrf_field_name'    => '_csrf_token',
            'csrf_token_id'      => 'page_builder_content',
            'translation_domain' => 'NowoPageBuilderKitBundle',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
