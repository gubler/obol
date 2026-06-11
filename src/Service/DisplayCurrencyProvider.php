<?php

// ABOUTME: Returns the currency the app presents converted totals in - the configured display currency.
// ABOUTME: Bound to app.display_currency (default USD); the single seam that becomes per-user under SaaS.

declare(strict_types=1);

namespace App\Service;

use App\Enum\Currency;

final readonly class DisplayCurrencyProvider
{
    private Currency $displayCurrency;

    public function __construct(string $displayCurrency)
    {
        $this->displayCurrency = Currency::from($displayCurrency);
    }

    public function get(): Currency
    {
        return $this->displayCurrency;
    }
}
