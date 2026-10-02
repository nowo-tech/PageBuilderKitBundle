<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo firewall login (_username / _password / authenticate CSRF).
 *
 * @extends AbstractType<array{_username?: string, _password?: string}>
 */
final class DemoLoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', TextType::class, [
                'label'    => 'Username',
                'required' => true,
                'attr'     => [
                    'class'        => 'form-control',
                    'autocomplete' => 'username',
                    'autofocus'    => true,
                ],
            ])
            ->add('_password', PasswordType::class, [
                'label'    => 'Password',
                'required' => true,
                'attr'     => [
                    'class'        => 'form-control',
                    'autocomplete' => 'current-password',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'authenticate',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
