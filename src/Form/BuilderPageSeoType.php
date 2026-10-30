<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\UiKitBundle\Twig\TranslatableText;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<BuilderPageTranslation>
 */
#[FormKitConfig('page_builder_kit')]
final class BuilderPageSeoType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'title', TextType::class, [
            'label'       => 'form.page_title',
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
        ]);
        $this->addWithDefaults($builder, 'slug', TextType::class, [
            'label'       => 'form.page_slug',
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
        ]);
        $this->addWithDefaults($builder, 'metaTitle', TextType::class, [
            'label'       => 'form.meta_title',
            'required'    => false,
            'constraints' => [new Assert\Length(max: 255)],
        ]);
        $this->addWithDefaults($builder, 'metaDescription', TextareaType::class, [
            'label'    => 'form.meta_description',
            'required' => false,
            'attr'     => ['rows' => 3],
        ]);
        $this->addWithDefaults($builder, 'ogTitle', TextType::class, [
            'label'       => 'form.og_title',
            'required'    => false,
            'constraints' => [new Assert\Length(max: 255)],
        ]);
        $this->addWithDefaults($builder, 'ogDescription', TextareaType::class, [
            'label'    => 'form.og_description',
            'required' => false,
            'attr'     => ['rows' => 3],
        ]);
        $this->addWithDefaults($builder, 'ogImage', TextType::class, [
            'label'       => 'form.og_image',
            'required'    => false,
            'attr'        => ['placeholder' => new TranslatableText('https://…')],
            'constraints' => [new Assert\Length(max: 512)],
        ]);
        $this->addWithDefaults($builder, 'canonicalUrl', TextType::class, [
            'label'       => 'form.canonical_url',
            'required'    => false,
            'attr'        => ['placeholder' => new TranslatableText('https://example.com/page')],
            'constraints' => [new Assert\Length(max: 512)],
        ]);
        $this->addWithDefaults($builder, 'robots', TextType::class, [
            'label'       => 'form.robots',
            'required'    => false,
            'attr'        => ['placeholder' => new TranslatableText('index,follow')],
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
