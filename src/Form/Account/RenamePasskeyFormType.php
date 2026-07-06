<?php

// ABOUTME: Passkey rename form - a single name field bound to RenamePasskeyDto.

declare(strict_types=1);

namespace App\Form\Account;

use App\Dto\Account\RenamePasskeyDto;
use App\Entity\PasskeyCredential;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RenamePasskeyDto>
 */
final class RenamePasskeyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'name', type: TextType::class, options: [
                'label' => 'account.passkey.name.label',
                'empty_data' => '',
                'attr' => [
                    'maxlength' => PasskeyCredential::NAME_MAX_LENGTH,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => RenamePasskeyDto::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'rename_passkey';
    }
}
