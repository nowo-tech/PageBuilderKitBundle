<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{pageKey: string, title?: string|null, include_field_schema?: bool, include_field_values?: bool, include_seo?: bool}>
 */
#[FormKitConfig('page_builder_kit')]
final class TemplateApplyType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'pageKey', TextType::class, [
            'label'       => 'form.page_key',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Regex(pattern: '/^[a-z0-9_-]+$/'),
            ],
            'attr' => ['placeholder' => 'form.page_key'],
        ]);
        $this->addWithDefaults($builder, 'title', TextType::class, [
            'label'    => 'form.page_title',
            'required' => false,
            'attr'     => ['placeholder' => 'form.page_title'],
        ]);
        $this->addWithDefaults($builder, 'include_field_schema', CheckboxType::class, [
            'label'    => 'admin.templates.include_field_schema',
            'required' => false,
            'data'     => true,
        ]);
        $this->addWithDefaults($builder, 'include_field_values', CheckboxType::class, [
            'label'    => 'admin.templates.include_field_values',
            'required' => false,
        ]);
        $this->addWithDefaults($builder, 'include_seo', CheckboxType::class, [
            'label'    => 'admin.templates.include_seo',
            'required' => false,
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
