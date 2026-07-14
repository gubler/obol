<?php

// ABOUTME: Form type for the admin System Toggles section - one checkbox per runtime system setting.
// ABOUTME: Bound to SystemTogglesData; grows by adding a field here as each new toggle lands.

declare(strict_types=1);

namespace App\Form\Admin;

use App\Dto\Admin\SystemTogglesData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SystemTogglesData>
 */
final class SystemTogglesFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('publicSignupEnabled', CheckboxType::class, [
            'label' => 'admin.system_toggles.public_signup',
            'help' => 'admin.system_toggles.public_signup_help',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SystemTogglesData::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'system_toggles';
    }
}
