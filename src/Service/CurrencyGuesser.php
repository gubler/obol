<?php

// ABOUTME: Guesses a new user's display currency from their browser's Accept-Language region.
// ABOUTME: Only ever suggests a currency Obol supports (the Currency enum); falls back to USD otherwise.

declare(strict_types=1);

namespace App\Service;

use App\Enum\Currency;
use Symfony\Component\HttpFoundation\Request;

final class CurrencyGuesser
{
    /**
     * ISO 3166-1 region -> the region's everyday currency, restricted to the currencies Obol supports
     * (Currency enum). Eurozone members all map to EUR. Regions absent here fall back to USD, so this
     * only needs to cover the regions whose currency we can actually offer.
     *
     * @var array<string, string>
     */
    private const array REGION_TO_CURRENCY = [
        'AU' => 'AUD',
        'BR' => 'BRL',
        'CA' => 'CAD',
        'CH' => 'CHF', 'LI' => 'CHF',
        'CN' => 'CNY',
        'CZ' => 'CZK',
        'DK' => 'DKK',
        // Eurozone.
        'AT' => 'EUR', 'BE' => 'EUR', 'HR' => 'EUR', 'CY' => 'EUR', 'EE' => 'EUR', 'FI' => 'EUR',
        'FR' => 'EUR', 'DE' => 'EUR', 'GR' => 'EUR', 'IE' => 'EUR', 'IT' => 'EUR', 'LV' => 'EUR',
        'LT' => 'EUR', 'LU' => 'EUR', 'MT' => 'EUR', 'NL' => 'EUR', 'PT' => 'EUR', 'SK' => 'EUR',
        'SI' => 'EUR', 'ES' => 'EUR',
        'GB' => 'GBP',
        'HK' => 'HKD',
        'HU' => 'HUF',
        'ID' => 'IDR',
        'IL' => 'ILS',
        'IN' => 'INR',
        'IS' => 'ISK',
        'JP' => 'JPY',
        'KR' => 'KRW',
        'MX' => 'MXN',
        'MY' => 'MYR',
        'NO' => 'NOK',
        'NZ' => 'NZD',
        'PH' => 'PHP',
        'PL' => 'PLN',
        'RO' => 'RON',
        'SE' => 'SEK',
        'SG' => 'SGD',
        'TH' => 'THB',
        'TR' => 'TRY',
        'US' => 'USD',
        'ZA' => 'ZAR',
    ];

    public function guessFrom(Request $request): Currency
    {
        $preferred = $request->getLanguages()[0] ?? null;
        if (null === $preferred) {
            return Currency::USD;
        }

        $region = \Locale::getRegion($preferred);
        if (null === $region || '' === $region) {
            return Currency::USD;
        }

        if (!isset(self::REGION_TO_CURRENCY[$region])) {
            return Currency::USD;
        }

        return Currency::from(self::REGION_TO_CURRENCY[$region]);
    }
}
