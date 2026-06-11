<?php

// ABOUTME: ISO 4217 currencies Obol supports - the set the ECB publishes (and Frankfurter serves) rates for.
// ABOUTME: Curated, not all of ISO 4217, so every currency offered is one the Converter can handle. See #126.

declare(strict_types=1);

namespace App\Enum;

use Symfony\Component\Intl\Currencies;

enum Currency: string
{
    case AUD = 'AUD';
    case BRL = 'BRL';
    case CAD = 'CAD';
    case CHF = 'CHF';
    case CNY = 'CNY';
    case CZK = 'CZK';
    case DKK = 'DKK';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case HKD = 'HKD';
    case HUF = 'HUF';
    case IDR = 'IDR';
    case ILS = 'ILS';
    case INR = 'INR';
    case ISK = 'ISK';
    case JPY = 'JPY';
    case KRW = 'KRW';
    case MXN = 'MXN';
    case MYR = 'MYR';
    case NOK = 'NOK';
    case NZD = 'NZD';
    case PHP = 'PHP';
    case PLN = 'PLN';
    case RON = 'RON';
    case SEK = 'SEK';
    case SGD = 'SGD';
    case THB = 'THB';
    case TRY = 'TRY';
    case USD = 'USD';
    case ZAR = 'ZAR';

    /**
     * The number of minor-unit decimal places for this currency (e.g. 2 for USD, 0 for JPY).
     */
    public function fractionDigits(): int
    {
        return Currencies::getFractionDigits($this->value);
    }

    /**
     * The currency symbol in English (e.g. "$", "¥"). Pinned to English until i18n lands (#116).
     */
    public function symbol(): string
    {
        return Currencies::getSymbol($this->value, 'en');
    }

    /**
     * The currency's full English name (e.g. "US Dollar"), for form choices and display.
     */
    public function label(): string
    {
        return Currencies::getName($this->value, 'en');
    }
}
