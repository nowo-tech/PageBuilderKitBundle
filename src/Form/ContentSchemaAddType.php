<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\UiKitBundle\Twig\TranslatableText;
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
#[FormKitConfig('page_builder_kit')]
final class ContentSchemaAddType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeChoices = [];
        foreach (ContentFieldType::values() as $type) {
            $typeChoices[$type] = $type;
        }

        $this->addWithDefaults($builder, 'schema_action', HiddenType::class, [
            'data' => 'add',
        ]);
        $this->addWithDefaults($builder, 'key', TextType::class, [
            'label'       => 'admin.content.field_key',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Regex(pattern: '/^[a-z][a-z0-9_]*$/'),
            ],
            'attr' => ['placeholder' => new TranslatableText('hero_title')],
        ]);
        $this->addWithDefaults($builder, 'type', ChoiceType::class, [
            'label'                     => 'admin.content.field_type',
            'choices'                   => $typeChoices,
            'choice_translation_domain' => false,
        ]);
        $this->addWithDefaults($builder, 'label', TextType::class, [
            'label'    => 'admin.content.field_label',
            'required' => false,
            'attr'     => ['placeholder' => new TranslatableText('Hero title')],
        ]);
        $this->addWithDefaults($builder, 'options', TextType::class, [
            'label'    => 'admin.content.field_options',
            'required' => false,
            'attr'     => ['placeholder' => new TranslatableText('a,b,c')],
        ]);
        $this->addWithDefaults($builder, 'subfields', TextType::class, [
            'label'    => 'admin.content.field_subfields',
            'required' => false,
            'attr'     => ['placeholder' => new TranslatableText('question:string,answer:text')],
            'help'     => 'admin.content.field_subfields_hint',
        ]);
        $this->addWithDefaults($builder, 'min', IntegerType::class, [
            'label'    => 'admin.content.field_min',
            'required' => false,
            'attr'     => ['min' => 0, 'placeholder' => new TranslatableText('0')],
        ]);
        $this->addWithDefaults($builder, 'max', IntegerType::class, [
            'label'    => 'admin.content.field_max',
            'required' => false,
            'attr'     => ['min' => 0],
        ]);
        $this->addWithDefaults($builder, 'required', CheckboxType::class, [
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
