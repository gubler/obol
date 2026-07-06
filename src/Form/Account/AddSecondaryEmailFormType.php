<?php

// ABOUTME: Form type for adding a secondary email address on /account/emails.
// ABOUTME: Bound to AddSecondaryEmailDto; a single email field, CSRF-protected by default.

declare(strict_types=1);

namespace App\Form\Account;

use App\Dto\Account\AddSecondaryEmailDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AddSecondaryEmailDto>
 */
final class AddSecondaryEmailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'account.email.add.label',
            'empty_data' => '',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddSecondaryEmailDto::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'add_secondary_email';
    }
}
