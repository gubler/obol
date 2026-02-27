<?php

// ABOUTME: Symfony form type for creating new payments with amount and paid date fields.
// ABOUTME: Maps form fields to CreatePaymentDto with validation constraints.

declare(strict_types=1);

namespace App\Form\Payment;

use App\Dto\Payment\CreatePaymentDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CreatePaymentDto>
 */
final class CreatePaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'amount', type: NumberType::class, options: [
                'label' => 'Amount (cents)',
            ])
            ->add(child: 'paidDate', type: DateType::class, options: [
                'label' => 'Paid Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => CreatePaymentDto::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'create_payment';
    }
}
