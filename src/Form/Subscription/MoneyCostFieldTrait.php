<?php

// ABOUTME: Shared wiring for the subscription "Cost" field, entered in major units.
// ABOUTME: Re-adds the field with the active currency's fraction digits (cost scales with currency).

declare(strict_types=1);

namespace App\Form\Subscription;

use App\Dto\Subscription\CreateSubscriptionDto;
use App\Dto\Subscription\UpdateSubscriptionDto;
use App\Enum\Currency;
use App\Form\Type\MoneyMinorFormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

trait MoneyCostFieldTrait
{
    /**
     * Add the Cost field in its natural position, then re-add it (preserving that position) once the
     * active currency is known - from the prefilled DTO on display, and from the submitted currency on
     * bind - so the major-unit input scales by the right number of fraction digits.
     *
     * @template TData
     *
     * @param FormBuilderInterface<TData> $builder
     */
    private function addCostField(FormBuilderInterface $builder): void
    {
        $this->putCostField($builder, Currency::USD->fractionDigits());

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            $currency = $data instanceof CreateSubscriptionDto || $data instanceof UpdateSubscriptionDto
                ? $data->currency
                : Currency::USD;
            $this->putCostField($event->getForm(), $currency->fractionDigits());
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $submitted = $event->getData();
            $value = \is_array($submitted) ? ($submitted['currency'] ?? null) : null;
            if (!\is_string($value) || '' === $value) {
                // No currency in this submission (e.g. the field is locked); keep the scaling that
                // PRE_SET_DATA established from the subscription's existing currency.
                return;
            }

            $currency = Currency::tryFrom($value) ?? Currency::USD;
            $this->putCostField($event->getForm(), $currency->fractionDigits());
        });
    }

    /**
     * @template TData
     *
     * @param FormBuilderInterface<TData>|FormInterface<TData> $form
     */
    private function putCostField(FormBuilderInterface|FormInterface $form, int $fractionDigits): void
    {
        $form->add(child: 'cost', type: MoneyMinorFormType::class, options: [
            'label' => 'subscription.form.cost',
            'fraction_digits' => $fractionDigits,
        ]);
    }
}
