<?php

// ABOUTME: Unit tests for FindTotalObligationRunner - the total-obligation query behind the homepage capstone.
// ABOUTME: Sums active subs' monthlyCost by currency, converts to the display currency, scales week/month/year.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\FindTotalObligationQuery;
use App\Message\Query\Report\FindTotalObligationRunner;
use App\Message\Query\Report\TotalObligation;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;

function makeObligationSubscription(Money $cost, PaymentPeriod $period, int $count = 1): Subscription
{
    return new Subscription(
        category: new Category(name: 'Test'),
        name: 'Test',
        nextRenewal: new DateTimeImmutable('2020-01-01'),
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: $cost,
    );
}

/**
 * @param list<Subscription>   $subscriptions
 * @param array<string, float> $rates         EUR-pivot rates by currency code (units per 1 EUR)
 */
function runTotalObligation(array $subscriptions, array $rates, string $displayCurrency = 'USD'): TotalObligation
{
    $subscriptionRepository = test()->createMock(SubscriptionRepository::class);
    $subscriptionRepository->method('findBy')->with(['archived' => false])->willReturn($subscriptions);

    $exchangeRateRepository = test()->createMock(ExchangeRateRepository::class);
    $exchangeRateRepository->method('latestRate')
        ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
    ;

    $runner = new FindTotalObligationRunner(
        $subscriptionRepository,
        new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider($displayCurrency)),
    );

    return $runner(new FindTotalObligationQuery());
}

test('sums active subscriptions, converts to the display currency, and keeps a native breakdown', function (): void {
    $totals = runTotalObligation(
        subscriptions: [
            makeObligationSubscription(new Money(4000, Currency::USD), PaymentPeriod::Month),  // 4000 USD/mo
            makeObligationSubscription(new Money(12000, Currency::USD), PaymentPeriod::Year),   // 1000 USD/mo
            makeObligationSubscription(new Money(3000, Currency::EUR), PaymentPeriod::Month),   // 3000 EUR/mo -> 3240 USD
        ],
        rates: ['EUR' => 1.0, 'USD' => 1.08],
    );

    expect($totals)->toBeInstanceOf(TotalObligation::class)
        ->and($totals->monthly->minorAmount)->toBe(8240)        // 5000 USD + 3240 USD
        ->and($totals->monthly->currency)->toBe(Currency::USD)
        ->and($totals->weekly->minorAmount)->toBe(1902)         // round(8240 * 12/52)
        ->and($totals->yearly->minorAmount)->toBe(98880)        // 8240 * 12
        ->and($totals->isApproximate)->toBeTrue()
        ->and($totals->asOf)->toBeInstanceOf(DateTimeImmutable::class)
    ;

    expect($totals->breakdown)->toHaveCount(2);
    // Key-sorted by currency code: EUR before USD.
    expect($totals->breakdown[0]->currency)->toBe(Currency::EUR)
        ->and($totals->breakdown[0]->minorAmount)->toBe(3000)
        ->and($totals->breakdown[1]->currency)->toBe(Currency::USD)
        ->and($totals->breakdown[1]->minorAmount)->toBe(5000)
    ;
});

test('scales week and year from the monthly total when nothing needs converting', function (): void {
    $totals = runTotalObligation(
        subscriptions: [makeObligationSubscription(new Money(5800, Currency::USD), PaymentPeriod::Month)],
        rates: [],
    );

    expect($totals->monthly->minorAmount)->toBe(5800)
        ->and($totals->weekly->minorAmount)->toBe(1338)    // round(5800 * 12/52)
        ->and($totals->yearly->minorAmount)->toBe(69600)   // 5800 * 12
        ->and($totals->isApproximate)->toBeFalse()
        ->and($totals->breakdown)->toHaveCount(1)
    ;
});

test('reports a zero total in the display currency when there are no active subscriptions', function (): void {
    $totals = runTotalObligation(subscriptions: [], rates: []);

    expect($totals->monthly->minorAmount)->toBe(0)
        ->and($totals->monthly->currency)->toBe(Currency::USD)
        ->and($totals->weekly->minorAmount)->toBe(0)
        ->and($totals->yearly->minorAmount)->toBe(0)
        ->and($totals->isApproximate)->toBeFalse()
        ->and($totals->breakdown)->toBe([])
    ;
});
