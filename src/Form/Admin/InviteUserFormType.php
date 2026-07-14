<?php

// ABOUTME: Form type for the admin invite-user form - a single email field bound to InviteUserData.

declare(strict_types=1);

namespace App\Form\Admin;

use App\Dto\Admin\InviteUserData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InviteUserData>
 */
final class InviteUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'admin.users.invite.email',
            'attr' => ['placeholder' => 'admin.users.invite.email_placeholder'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InviteUserData::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'invite_user';
    }
}
