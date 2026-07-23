<?php

// ABOUTME: Renders a Money as localized currency via ICU - e.g. en "$15.99"/"¥2,000", de_DE "1.599,99 $".
// ABOUTME: The single money-formatting seam; locale comes from LocaleSwitcher, never the \Locale global.

declare(strict_types=1);

namespace App\Service\Money;

use App\ValueObject\Money;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class MoneyFormatter
{
    public function __construct(
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    /**
     * Fraction digits follow the currency's stored convention rather than ICU's default, so a
     * zero-decimal currency (JPY, HUF) never gains spurious decimals. See ADR-0025.
     */
    public function format(Money $money): string
    {
        $digits = $money->currency->fractionDigits();

        $formatter = new \NumberFormatter($this->localeSwitcher->getLocale(), \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $digits);

        return (string) $formatter->formatCurrency($money->minorAmount / (10 ** $digits), $money->currency->value);
    }
}
