<?php

// ABOUTME: Symfony form type for the first-run onboarding screen, mapped to CompleteOnboardingDto.
// ABOUTME: Name is optional; currency reads "<symbol> <ISO>"; timezone carries a hook for browser detection.

declare(strict_types=1);

namespace App\Form\Onboarding;

use App\Dto\Onboarding\CompleteOnboardingDto;
use App\Enum\Currency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CompleteOnboardingDto>
 */
final class CompleteOnboardingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'displayName', type: TextType::class, options: [
                'label' => 'onboarding.form.display_name',
                'required' => false,
                'attr' => ['placeholder' => 'onboarding.form.display_name_placeholder'],
            ])
            ->add(child: 'displayCurrency', type: EnumType::class, options: [
                'class' => Currency::class,
                'label' => 'onboarding.form.currency',
                // Each option reads "<symbol> <ISO>" (e.g. "$ USD"), matching the subscription form.
                'choice_label' => static fn (Currency $currency): string => $currency->symbol() . ' ' . $currency->value,
            ])
            ->add(child: 'timezone', type: TimezoneType::class, options: [
                'label' => 'onboarding.form.timezone',
                // The controller pre-selects the account's timezone; timezone_detect_controller.js
                // refines it to the browser's actual zone before the user touches the field.
                'attr' => ['data-timezone-detect-target' => 'field'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => CompleteOnboardingDto::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'onboarding';
    }
}
