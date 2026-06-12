<?php

// ABOUTME: Unit tests for CurrencyTotaller - sums a mixed-currency list into a converted display total.
// ABOUTME: Returns a ConvertedTotal: the converted headline, the native per-currency breakdown, approximate flag.

declare(strict_types=1);

use App\Enum\Currency;
use App\Message\Currency\ConvertedTotal;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\ExchangeRateRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;

/**
 * @param list<Money>          $amounts
 * @param array<string, float> $rates   EUR-pivot rates by currency code (units per 1 EUR)
 */
function totalAmounts(array $amounts, array $rates, string $displayCurrency = 'USD'): ConvertedTotal
{
    $exchangeRateRepository = test()->createMock(ExchangeRateRepository::class);
    $exchangeRateRepository->method('latestRate')
        ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
    ;

    $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider($displayCurrency));

    return $totaller->total($amounts);
}

test('totals a mixed-currency list, converts to the display currency, and keeps a key-sorted breakdown', function (): void {
    $total = totalAmounts(
        amounts: [
            new Money(4000, Currency::USD),
            new Money(1000, Currency::USD),
            new Money(3000, Currency::EUR), // -> 3240 USD
        ],
        rates: ['EUR' => 1.0, 'USD' => 1.08],
    );

    expect($total)->toBeInstanceOf(ConvertedTotal::class)
        ->and($total->converted->minorAmount)->toBe(8240)   // 5000 USD + 3240 USD
        ->and($total->converted->currency)->toBe(Currency::USD)
        ->and($total->isApproximate)->toBeTrue()
    ;

    expect($total->breakdown)->toHaveCount(2);
    // Key-sorted by currency code: EUR before USD; same-currency amounts are merged.
    expect($total->breakdown[0]->currency)->toBe(Currency::EUR)
        ->and($total->breakdown[0]->minorAmount)->toBe(3000)
        ->and($total->breakdown[1]->currency)->toBe(Currency::USD)
        ->and($total->breakdown[1]->minorAmount)->toBe(5000)
    ;
});

test('is not approximate and needs no rate for a single display-currency list', function (): void {
    $total = totalAmounts(amounts: [new Money(5800, Currency::USD)], rates: []);

    expect($total->converted->minorAmount)->toBe(5800)
        ->and($total->isApproximate)->toBeFalse()
        ->and($total->breakdown)->toHaveCount(1)
    ;
});

test('returns a zero display-currency total for an empty list', function (): void {
    $total = totalAmounts(amounts: [], rates: []);

    expect($total->converted->minorAmount)->toBe(0)
        ->and($total->converted->currency)->toBe(Currency::USD)
        ->and($total->isApproximate)->toBeFalse()
        ->and($total->breakdown)->toBe([])
    ;
});
