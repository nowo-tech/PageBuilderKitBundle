<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<BuilderPageTranslation>
 */
final class BuilderPageSeoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $row = ['class' => 'mb-0'];

        $builder
            ->add('title', TextType::class, [
                'label'       => 'form.page_title',
                'row_attr'    => $row,
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('slug', TextType::class, [
                'label'       => 'form.page_slug',
                'row_attr'    => $row,
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('metaTitle', TextType::class, [
                'label'       => 'form.meta_title',
                'required'    => false,
                'row_attr'    => $row,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('metaDescription', TextareaType::class, [
                'label'    => 'form.meta_description',
                'required' => false,
                'attr'     => ['rows' => 3],
                'row_attr' => $row,
            ])
            ->add('ogTitle', TextType::class, [
                'label'       => 'form.og_title',
                'required'    => false,
                'row_attr'    => $row,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('ogDescription', TextareaType::class, [
                'label'    => 'form.og_description',
                'required' => false,
                'attr'     => ['rows' => 3],
                'row_attr' => $row,
            ])
            ->add('ogImage', TextType::class, [
                'label'       => 'form.og_image',
                'required'    => false,
                'attr'        => ['placeholder' => 'https://…'],
                'row_attr'    => $row,
                'constraints' => [new Assert\Length(max: 512)],
            ])
            ->add('canonicalUrl', TextType::class, [
                'label'       => 'form.canonical_url',
                'required'    => false,
                'attr'        => ['placeholder' => 'https://example.com/page'],
                'row_attr'    => $row,
                'constraints' => [new Assert\Length(max: 512)],
            ])
            ->add('robots', TextType::class, [
                'label'       => 'form.robots',
                'required'    => false,
                'attr'        => ['placeholder' => 'index,follow'],
                'row_attr'    => $row,
                'constraints' => [new Assert\Length(max: 64)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => BuilderPageTranslation::class,
            'translation_domain' => 'NowoPageBuilderKitBundle',
        ]);
    }
}
