<?php

// ABOUTME: Login form - a single email field bound to LoginRequestDto for requesting a magic link.

declare(strict_types=1);

namespace App\Form\Auth;

use App\Dto\Auth\LoginRequestDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LoginRequestDto>
 */
final class LoginRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'email', type: EmailType::class, options: [
                'label' => 'auth.login.email.label',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'email',
                    'autofocus' => true,
                    'inputmode' => 'email',
                    'placeholder' => 'auth.login.email.placeholder',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => LoginRequestDto::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'login_request';
    }
}
