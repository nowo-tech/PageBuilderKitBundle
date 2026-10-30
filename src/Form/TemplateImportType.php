<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{payload: string}>
 */
#[FormKitConfig('page_builder_kit')]
final class TemplateImportType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'payload', TextareaType::class, [
            'label'       => 'admin.templates.payload',
            'constraints' => [new Assert\NotBlank()],
            'attr'        => [
                'class'       => 'pbk-input--code',
                'rows'        => 6,
                'placeholder' => 'admin.templates.payload',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection'    => true,
            'csrf_field_name'    => '_csrf_token',
            'csrf_token_id'      => 'page_builder_document',
            'translation_domain' => 'NowoPageBuilderKitBundle',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
