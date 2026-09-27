<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class BuilderPageCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $row = ['class' => 'mb-0'];

        $builder
            ->add('pageKey', TextType::class, [
                'label'       => 'form.page_key',
                'row_attr'    => $row,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^[a-z0-9_-]+$/'),
                ],
            ])
            ->add('title', TextType::class, [
                'label'       => 'form.page_title',
                'row_attr'    => $row,
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
