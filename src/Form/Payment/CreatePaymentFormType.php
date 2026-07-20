<?php

// ABOUTME: Symfony form type for creating new payments with amount and paid date fields.
// ABOUTME: For a manual subscription it also offers a "restart automatic payments" control.

declare(strict_types=1);

namespace App\Form\Payment;

use App\Dto\Payment\CreatePaymentDto;
use App\Form\Type\MoneyMinorFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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
            ->add(child: 'amount', type: MoneyMinorFormType::class, options: [
                'label' => 'payment.form.amount',
                'fraction_digits' => $options['fraction_digits'],
            ])
            ->add(child: 'paidDate', type: DateType::class, options: [
                'label' => 'payment.form.paid_date',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'Y-m-d',
            ])
        ;

        // Resuming automated generation is only meaningful for a subscription the user has taken
        // over manually (by deleting its latest payment).
        if (true === $options['offer_restart']) {
            $builder
                ->add(child: 'restartPaymentGeneration', type: CheckboxType::class, options: [
                    'label' => 'payment.form.restart_payments',
                    'required' => false,
                ])
                ->add(child: 'nextRenewal', type: DateType::class, options: [
                    'label' => 'payment.form.next_renewal_on',
                    'widget' => 'single_text',
                    'input' => 'string',
                    'input_format' => 'Y-m-d',
                    'required' => false,
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => CreatePaymentDto::class,
            'offer_restart' => false,
            'fraction_digits' => 2,
        ]);
        $resolver->setAllowedTypes(option: 'offer_restart', allowedTypes: 'bool');
        $resolver->setAllowedTypes(option: 'fraction_digits', allowedTypes: 'int');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'create_payment';
    }
}
