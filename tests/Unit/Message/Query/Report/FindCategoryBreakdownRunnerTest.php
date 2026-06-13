<?php

// ABOUTME: Unit tests for FindCategoryBreakdownRunner - one category's subscriptions as a composition pie.
// ABOUTME: Resolves the category (null when missing), converts each active subscription's monthly share.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryBreakdownQuery;
use App\Message\Query\Report\FindCategoryBreakdownRunner;
use App\Repository\CategoryRepository;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;
use Symfony\Component\Uid\Ulid;

function breakdownSubscription(Category $category, string $name, int $costMinor, Currency $currency = Currency::USD): Subscription
{
    return new Subscription(
        category: $category,
        name: $name,
        nextRenewal: new DateTimeImmutable('2026-01-01'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: new Money($costMinor, $currency),
    );
}

/**
 * @param list<Subscription>   $subscriptions
 * @param array<string, float> $rates
 */
function runBreakdown(?Category $category, array $subscriptions = [], array $rates = []): ?Composition
{
    $categoryId = $category?->id ?? new Ulid();

    $categoryRepository = test()->createMock(CategoryRepository::class);
    $categoryRepository->method('find')->with($categoryId)->willReturn($category);

    $subscriptionRepository = test()->createMock(SubscriptionRepository::class);
    $subscriptionRepository->method('findBy')
        ->with(['archived' => false, 'category' => $category])
        ->willReturn($subscriptions)
    ;

    $exchangeRateRepository = test()->createMock(ExchangeRateRepository::class);
    $exchangeRateRepository->method('latestRate')
        ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
    ;
    $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));

    $runner = new FindCategoryBreakdownRunner($categoryRepository, $subscriptionRepository, $totaller);

    return $runner(new FindCategoryBreakdownQuery($categoryId));
}

test('one slice per subscription, sorted by size, titled with the category name', function (): void {
    $streaming = new Category(name: 'Streaming');

    $composition = runBreakdown($streaming, [
        breakdownSubscription($streaming, 'Netflix', 1599),
        breakdownSubscription($streaming, 'Spotify', 1099),
    ]);

    expect($composition)->toBeInstanceOf(Composition::class)
        ->and($composition->title)->toBe('Streaming')
        ->and($composition->slices)->toHaveCount(2)
        ->and($composition->slices[0]->label)->toBe('Netflix')   // 1599, largest first
        ->and($composition->slices[0]->converted->minorAmount)->toBe(1599)
        ->and($composition->slices[0]->id)->toBeNull()           // leaf slice, no deeper drill-down
        ->and($composition->slices[1]->label)->toBe('Spotify')
        ->and($composition->total->converted->minorAmount)->toBe(2698)
    ;
});

test('returns null when the category does not exist', function (): void {
    expect(runBreakdown(null))->toBeNull();
});

test('is an empty, zero-total pie for a category with no active subscriptions', function (): void {
    $empty = new Category(name: 'Empty');

    $composition = runBreakdown($empty, []);

    expect($composition)->toBeInstanceOf(Composition::class)
        ->and($composition->title)->toBe('Empty')
        ->and($composition->slices)->toBe([])
        ->and($composition->total->converted->minorAmount)->toBe(0)
    ;
});
