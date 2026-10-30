<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('page_builder_kit')]
final class BuilderPageCreateType extends AbstractType
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
        ]);
        $this->addWithDefaults($builder, 'title', TextType::class, [
            'label'       => 'form.page_title',
            'constraints' => [new Assert\NotBlank()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'NowoPageBuilderKitBundle',
        ]);
    }
}
