<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Add a content-field schema row (CSRF id: page_builder_content).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ContentSchemaAddType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeChoices = [];
        foreach (ContentFieldType::values() as $type) {
            $typeChoices[$type] = $type;
        }

        $builder
            ->add('schema_action', HiddenType::class, [
                'data' => 'add',
            ])
            ->add('key', TextType::class, [
                'label'       => 'admin.content.field_key',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^[a-z][a-z0-9_]*$/'),
                ],
                'attr' => ['placeholder' => 'hero_title', 'class' => 'form-control'],
            ])
            ->add('type', ChoiceType::class, [
                'label'   => 'admin.content.field_type',
                'choices' => $typeChoices,
                'attr'    => ['class' => 'form-select'],
            ])
            ->add('label', TextType::class, [
                'label'    => 'admin.content.field_label',
                'required' => false,
                'attr'     => ['placeholder' => 'Hero title', 'class' => 'form-control'],
            ])
            ->add('options', TextType::class, [
                'label'    => 'admin.content.field_options',
                'required' => false,
                'attr'     => ['placeholder' => 'a,b,c', 'class' => 'form-control'],
            ])
            ->add('subfields', TextType::class, [
                'label'    => 'admin.content.field_subfields',
                'required' => false,
                'attr'     => ['placeholder' => 'question:string,answer:text', 'class' => 'form-control'],
                'help'     => 'admin.content.field_subfields_hint',
            ])
            ->add('min', IntegerType::class, [
                'label'    => 'admin.content.field_min',
                'required' => false,
                'attr'     => ['min' => 0, 'placeholder' => '0', 'class' => 'form-control'],
            ])
            ->add('max', IntegerType::class, [
                'label'    => 'admin.content.field_max',
                'required' => false,
                'attr'     => ['min' => 0, 'class' => 'form-control'],
            ])
            ->add('required', CheckboxType::class, [
                'label'    => 'admin.content.field_required',
                'required' => false,
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
