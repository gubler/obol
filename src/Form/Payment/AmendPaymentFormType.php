<?php

// ABOUTME: Symfony form type for amending a payment's amount and paid date.
// ABOUTME: Maps form fields to AmendPaymentDto with validation constraints.

declare(strict_types=1);

namespace App\Form\Payment;

use App\Dto\Payment\AmendPaymentDto;
use App\Form\Type\MoneyMinorFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AmendPaymentDto>
 */
final class AmendPaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'amount', type: MoneyMinorFormType::class, options: [
                'label' => 'Amount',
                'fraction_digits' => $options['fraction_digits'],
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
            'data_class' => AmendPaymentDto::class,
            'fraction_digits' => 2,
        ]);
        $resolver->setAllowedTypes(option: 'fraction_digits', allowedTypes: 'int');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'amend_payment';
    }
}
