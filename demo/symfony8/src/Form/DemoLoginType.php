<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo firewall login (_username / _password / authenticate CSRF), a FormKit form (profile `demo`, REQ-DEMO-015).
 *
 * @extends AbstractType<array{_username?: string, _password?: string}>
 */
#[FormKitConfig('demo')]
final class DemoLoginType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, '_username', [
            'label'              => 'Username',
            'required'           => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['autocomplete' => 'username', 'autofocus' => true],
        ]);
        $this->addWithDefaults($builder, '_password', PasswordType::class, [
            'label'              => 'Password',
            'required'           => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['autocomplete' => 'current-password'],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => 'Sign in',
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--primary', 'form' => 'demo-login-form'],
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
