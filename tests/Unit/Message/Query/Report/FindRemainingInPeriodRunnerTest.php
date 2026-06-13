<?php

// ABOUTME: Unit tests for FindRemainingInPeriodRunner - what is still owed by week/month/year boundaries.
// ABOUTME: Projects nextRenewal forward against calendar period ends; a MockClock fixes "now".

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\FindRemainingInPeriodQuery;
use App\Message\Query\Report\FindRemainingInPeriodRunner;
use App\Message\Query\Report\RemainingInPeriod;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\Service\PeriodBoundaries;
use App\ValueObject\Money;
use Symfony\Component\Clock\MockClock;

function remainingSubscription(int $costMinor, string $nextRenewal, Currency $currency = Currency::USD, PaymentPeriod $period = PaymentPeriod::Month, int $count = 1): Subscription
{
    return new Subscription(
        category: new Category(name: 'Test'),
        name: 'Test',
        nextRenewal: new DateTimeImmutable($nextRenewal),
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: new Money($costMinor, $currency),
    );
}

/**
 * @param list<Subscription>   $subscriptions
 * @param array<string, float> $rates
 */
function runRemaining(array $subscriptions, string $now, array $rates = []): RemainingInPeriod
{
    $repository = test()->createMock(SubscriptionRepository::class);
    $repository->method('findBy')->with(['archived' => false])->willReturn($subscriptions);

    $exchangeRateRepository = test()->createMock(ExchangeRateRepository::class);
    $exchangeRateRepository->method('latestRate')
        ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
    ;
    $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));

    $runner = new FindRemainingInPeriodRunner($repository, new PeriodBoundaries(0), $totaller, new MockClock(new DateTimeImmutable($now)));

    return $runner(new FindRemainingInPeriodQuery());
}

test('projects renewals against the week, month, and year boundaries', function (): void {
    // $100/mo, next unpaid renewal June 1; "now" is mid-June.
    $remaining = runRemaining([remainingSubscription(10000, '2026-06-01')], now: '2026-06-15');

    expect($remaining)->toBeInstanceOf(RemainingInPeriod::class)
        ->and($remaining->weekly->converted->minorAmount)->toBe(10000)    // just June 1
        ->and($remaining->monthly->converted->minorAmount)->toBe(10000)   // just June 1
        ->and($remaining->yearly->converted->minorAmount)->toBe(70000)    // June..December = 7
        ->and($remaining->yearly->isApproximate)->toBeFalse()
    ;
});

test('includes overdue renewals (the unpaid-since-April example reads $300 in June)', function (): void {
    $remaining = runRemaining([remainingSubscription(10000, '2026-04-01')], now: '2026-06-15');

    // April, May, June all fall on or before end of June.
    expect($remaining->monthly->converted->minorAmount)->toBe(30000);
});

test('converts a multi-currency remaining total with a native breakdown', function (): void {
    $remaining = runRemaining(
        [
            remainingSubscription(10000, '2026-06-01'),                       // 100 USD due in June
            remainingSubscription(5000, '2026-06-01', Currency::EUR),         // 50 EUR -> 54 USD
        ],
        now: '2026-06-15',
        rates: ['EUR' => 1.0, 'USD' => 1.08],
    );

    expect($remaining->monthly->converted->minorAmount)->toBe(15400)   // 10000 USD + 5400 USD
        ->and($remaining->monthly->isApproximate)->toBeTrue()
        ->and($remaining->monthly->breakdown)->toHaveCount(2)
    ;
});

test('is zero across all periods when every renewal is beyond the year', function (): void {
    $remaining = runRemaining([remainingSubscription(10000, '2027-03-01')], now: '2026-06-15');

    expect($remaining->weekly->converted->minorAmount)->toBe(0)
        ->and($remaining->monthly->converted->minorAmount)->toBe(0)
        ->and($remaining->yearly->converted->minorAmount)->toBe(0)
        ->and($remaining->yearly->breakdown)->toBe([])
    ;
});
