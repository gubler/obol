<?php

// ABOUTME: Shared wiring for the subscription "Currency" dropdown that leads the cost amount input.
// ABOUTME: Each option shows the symbol plus ISO code (e.g. "$ USD").

declare(strict_types=1);

namespace App\Form\Subscription;

use App\Enum\Currency;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

trait CurrencyFieldTrait
{
    /**
     * Add the Currency dropdown that leads the cost amount: each option reads "<symbol> <ISO>"
     * (e.g. "$ USD"), so the selected symbol sits just before the amount.
     *
     * @template TData
     *
     * @param FormBuilderInterface<TData> $builder
     */
    private function addCurrencyField(FormBuilderInterface $builder, bool $disabled = false): void
    {
        $builder->add(child: 'currency', type: EnumType::class, options: [
            'class' => Currency::class,
            'label' => 'subscription.form.currency',
            'choice_label' => static fn (Currency $currency): string => $currency->symbol() . ' ' . $currency->value,
            // Locked once a payment exists: a disabled field is never bound from the request, so the
            // subscription keeps its currency regardless of what is submitted.
            'disabled' => $disabled,
        ]);
    }
}
