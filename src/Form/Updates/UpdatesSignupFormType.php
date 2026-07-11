<?php

// ABOUTME: Landing updates-signup form - a single email field bound to UpdatesSignupDto.

declare(strict_types=1);

namespace App\Form\Updates;

use App\Dto\Updates\UpdatesSignupDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<UpdatesSignupDto>
 */
final class UpdatesSignupFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'email', type: EmailType::class, options: [
                'label' => 'landing.updates.email.label',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                    'placeholder' => 'landing.updates.email.placeholder',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => UpdatesSignupDto::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'updates_signup';
    }
}
