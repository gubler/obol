<?php

// ABOUTME: Unit tests for FindCategoryCompositionRunner - each category's share of the monthly obligation.
// ABOUTME: Groups active subscriptions by category, converts each share to the display currency, sorts by size.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryCompositionQuery;
use App\Message\Query\Report\FindCategoryCompositionRunner;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;

function compositionSubscription(Category $category, int $costMinor, Currency $currency = Currency::USD, PaymentPeriod $period = PaymentPeriod::Month, int $count = 1): Subscription
{
    return new Subscription(
        category: $category,
        name: 'Test',
        nextRenewal: new DateTimeImmutable('2026-01-01'),
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: new Money($costMinor, $currency),
    );
}

/**
 * @param list<Subscription>   $subscriptions
 * @param array<string, float> $rates
 */
function runComposition(array $subscriptions, array $rates = [], string $displayCurrency = 'USD'): Composition
{
    $repository = test()->createMock(SubscriptionRepository::class);
    $repository->method('findBy')->with(['archived' => false])->willReturn($subscriptions);

    $exchangeRateRepository = test()->createMock(ExchangeRateRepository::class);
    $exchangeRateRepository->method('latestRate')
        ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
    ;
    $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider($displayCurrency));

    $runner = new FindCategoryCompositionRunner($repository, $totaller);

    return $runner(new FindCategoryCompositionQuery());
}

test('one slice per category, sorted by share descending, with the overall total', function (): void {
    $streaming = new Category(name: 'Streaming');
    $software = new Category(name: 'Software');

    $composition = runComposition([
        compositionSubscription($streaming, 1000),
        compositionSubscription($streaming, 500),
        compositionSubscription($software, 4000),
    ]);

    expect($composition)->toBeInstanceOf(Composition::class)
        ->and($composition->slices)->toHaveCount(2)
        ->and($composition->slices[0]->label)->toBe('Software')   // 4000, largest share first
        ->and($composition->slices[0]->converted->minorAmount)->toBe(4000)
        ->and($composition->slices[0]->id)->toBe($software->id)
        ->and($composition->slices[1]->label)->toBe('Streaming')  // 1500
        ->and($composition->slices[1]->converted->minorAmount)->toBe(1500)
        ->and($composition->total->converted->minorAmount)->toBe(5500)
        ->and($composition->total->isApproximate)->toBeFalse()
        ->and($composition->title)->toBeNull()
    ;
});

test('converts a mixed-currency category share and keeps the native breakdown', function (): void {
    $mixed = new Category(name: 'Mixed');

    $composition = runComposition(
        [
            compositionSubscription($mixed, 10000),                   // 100 USD
            compositionSubscription($mixed, 5000, Currency::EUR),     // 50 EUR -> 54 USD
        ],
        rates: ['EUR' => 1.0, 'USD' => 1.08],
    );

    expect($composition->slices)->toHaveCount(1)
        ->and($composition->slices[0]->converted->minorAmount)->toBe(15400)  // 10000 + 5400
        ->and($composition->slices[0]->isApproximate)->toBeTrue()
        ->and($composition->slices[0]->breakdown)->toHaveCount(2)            // native USD + EUR
        ->and($composition->total->converted->minorAmount)->toBe(15400)
        ->and($composition->total->isApproximate)->toBeTrue()
    ;
});

test('is empty with a zero total when there are no active subscriptions', function (): void {
    $composition = runComposition([]);

    expect($composition->slices)->toBe([])
        ->and($composition->total->converted->minorAmount)->toBe(0)
        ->and($composition->total->breakdown)->toBe([])
    ;
});
