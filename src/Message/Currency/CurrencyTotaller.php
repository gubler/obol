<?php

// ABOUTME: Sums a list of (possibly mixed-currency) Money into a ConvertedTotal in the display currency.
// ABOUTME: Groups by native currency, converts each group, and flags whether any conversion was needed.

declare(strict_types=1);

namespace App\Message\Currency;

use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;

final readonly class CurrencyTotaller
{
    public function __construct(
        private Converter $converter,
        private DisplayCurrencyProvider $displayCurrencyProvider,
    ) {
    }

    /**
     * @param array<Money> $amounts amounts in any mix of currencies; keys and order are irrelevant
     */
    public function total(array $amounts, ?\DateTimeImmutable $asOf = null): ConvertedTotal
    {
        $native = [];
        foreach ($amounts as $money) {
            $code = $money->currency->value;
            $native[$code] = isset($native[$code]) ? $native[$code]->add($money) : $money;
        }

        ksort($native);

        $display = $this->displayCurrencyProvider->get();
        $converted = new Money(0, $display);
        $approximate = false;
        foreach ($native as $money) {
            if ($money->currency !== $display) {
                $approximate = true;
            }

            $converted = $converted->add($this->converter->convert($money, $display, $asOf));
        }

        return new ConvertedTotal($converted, array_values($native), $approximate);
    }
}
