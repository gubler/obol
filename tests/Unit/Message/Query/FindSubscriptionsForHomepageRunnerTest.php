<?php

// ABOUTME: Unit tests for FindSubscriptionsForHomepageRunner verifying grouping, totals and sorting.
// ABOUTME: Mocks the repository; asserts group order, sort order, archived pass-through, and converted totals.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\SubscriptionSort;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Subscription\CategoryGroup;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageQuery;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageRunner;
use App\Message\Query\Subscription\HomepageListing;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;

function makeHomepageSubscription(
    Category $category,
    string $name,
    int $cost,
    PaymentPeriod $period = PaymentPeriod::Month,
    int $count = 1,
    string $renewal = '2024-01-01',
    Currency $currency = Currency::USD,
): Subscription {
    return new Subscription(
        category: $category,
        name: $name,
        nextRenewal: new DateTimeImmutable($renewal),
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: new Money($cost, $currency),
    );
}

/**
 * @param array<string, float> $rates EUR-pivot rates by currency code (units per 1 EUR)
 */
function homepageTotaller(array $rates = []): CurrencyTotaller
{
    $exchangeRateRepository = test()->createMock(ExchangeRateRepository::class);
    $exchangeRateRepository->method('latestRate')
        ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
    ;

    return new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));
}

/**
 * @return list<string>
 */
function names(array $subscriptions): array
{
    return array_map(static fn (Subscription $s): string => $s->name, $subscriptions);
}

/**
 * @param array<string, float> $rates
 */
function runHomepage(array $subscriptions, FindSubscriptionsForHomepageQuery $query, array $rates = []): HomepageListing
{
    $repository = test()->createMock(SubscriptionRepository::class);
    $repository->method('findForHomepage')->willReturn($subscriptions);

    return (new FindSubscriptionsForHomepageRunner($repository, homepageTotaller($rates)))($query);
}

test('groups by category ordered by category name, sorted by name within each group', function (): void {
    $alpha = new Category(name: 'Alpha');
    $beta = new Category(name: 'Beta');

    // Repository order is irrelevant - the runner imposes its own ordering.
    $subscriptions = [
        makeHomepageSubscription($beta, 'Pear', 2000),
        makeHomepageSubscription($alpha, 'Mango', 1500),
        makeHomepageSubscription($alpha, 'Apple', 1000),
    ];

    $listing = runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery());

    expect($listing)->toBeInstanceOf(HomepageListing::class)
        ->and($listing->groups)->toHaveCount(2)
        ->and($listing->groups[0])->toBeInstanceOf(CategoryGroup::class)
        ->and($listing->groups[0]->category)->toBe($alpha)
        ->and(names($listing->groups[0]->subscriptions))->toBe(['Apple', 'Mango'])
        ->and($listing->groups[1]->category)->toBe($beta)
        ->and(names($listing->groups[1]->subscriptions))->toBe(['Pear'])
    ;
});

test('returns a flat list sorted by name by default', function (): void {
    $alpha = new Category(name: 'Alpha');
    $beta = new Category(name: 'Beta');

    $subscriptions = [
        makeHomepageSubscription($beta, 'Pear', 2000),
        makeHomepageSubscription($alpha, 'Apple', 1000),
        makeHomepageSubscription($alpha, 'Mango', 1500),
    ];

    $listing = runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery());

    expect(names($listing->subscriptions))->toBe(['Apple', 'Mango', 'Pear']);
});

test('sorts by next renewal ascending across groups and within them', function (): void {
    $alpha = new Category(name: 'Alpha');
    $beta = new Category(name: 'Beta');

    $subscriptions = [
        makeHomepageSubscription($alpha, 'Mango', 1500, renewal: '2024-03-01'),
        makeHomepageSubscription($beta, 'Pear', 2000, renewal: '2024-02-01'),
        makeHomepageSubscription($alpha, 'Apple', 1000, renewal: '2024-01-01'),
    ];

    $listing = runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(sort: SubscriptionSort::Renewal));

    expect(names($listing->subscriptions))->toBe(['Apple', 'Pear', 'Mango'])
        ->and(names($listing->groups[0]->subscriptions))->toBe(['Apple', 'Mango'])
        ->and(names($listing->groups[1]->subscriptions))->toBe(['Pear'])
    ;
});

test('sorts by monthly cost descending, distinct from per-period cost', function (): void {
    $alpha = new Category(name: 'Alpha');

    $subscriptions = [
        // cost 12000/yr -> 1000/mo; cost 1500/mo -> 1500/mo; cost 2000/mo -> 2000/mo
        makeHomepageSubscription($alpha, 'Apple', 12000, PaymentPeriod::Year),
        makeHomepageSubscription($alpha, 'Mango', 1500),
        makeHomepageSubscription($alpha, 'Pear', 2000),
    ];

    $listing = runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(sort: SubscriptionSort::MonthlyCost));

    expect(names($listing->subscriptions))->toBe(['Pear', 'Mango', 'Apple']);
});

test('sorts by per-period cost descending', function (): void {
    $alpha = new Category(name: 'Alpha');

    $subscriptions = [
        makeHomepageSubscription($alpha, 'Apple', 12000, PaymentPeriod::Year),
        makeHomepageSubscription($alpha, 'Mango', 1500),
        makeHomepageSubscription($alpha, 'Pear', 2000),
    ];

    $listing = runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(sort: SubscriptionSort::Cost));

    expect(names($listing->subscriptions))->toBe(['Apple', 'Pear', 'Mango']);
});

test('sums each category monthly total', function (): void {
    $entertainment = new Category(name: 'Entertainment');

    $subscriptions = [
        makeHomepageSubscription($entertainment, 'Netflix', 1500),
        makeHomepageSubscription($entertainment, 'Spotify', 1000),
    ];

    $listing = runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery());

    expect($listing->groups[0]->monthlyTotal->converted->minorAmount)->toBe(2500)
        ->and($listing->groups[0]->monthlyTotal->isApproximate)->toBeFalse()
    ;
});

test('sums each category savings total across its subscriptions', function (): void {
    $software = new Category(name: 'Software');

    // A near renewal so the savings target is non-zero; it is constant within the day, so the runner's
    // "now" matches the value computed here.
    $asOf = new DateTimeImmutable();
    $renewal = $asOf->modify('+1 day')->format('Y-m-d');
    $perSubscription = makeHomepageSubscription($software, 'Solo', 1000, renewal: $renewal)->savingsTarget($asOf)->minorAmount;

    $listing = runHomepage([
        makeHomepageSubscription($software, 'JetBrains', 1000, renewal: $renewal),
        makeHomepageSubscription($software, '1Password', 1000, renewal: $renewal),
    ], new FindSubscriptionsForHomepageQuery());

    expect($perSubscription)->toBeGreaterThan(0)
        ->and($listing->groups[0]->savingsTotal->converted->minorAmount)->toBe(2 * $perSubscription)
    ;
});

test('converts a mixed-currency category to the display currency with a native breakdown', function (): void {
    $mixed = new Category(name: 'Mixed');

    $subscriptions = [
        makeHomepageSubscription($mixed, 'Dollar', 4000),                                   // 4000 USD/mo
        makeHomepageSubscription($mixed, 'Euro', 3000, currency: Currency::EUR),            // 3000 EUR/mo -> 3240 USD
    ];

    $listing = runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(), rates: ['EUR' => 1.0, 'USD' => 1.08]);

    $monthly = $listing->groups[0]->monthlyTotal;
    expect($monthly->converted->minorAmount)->toBe(7240)   // 4000 USD + 3240 USD
        ->and($monthly->converted->currency)->toBe(Currency::USD)
        ->and($monthly->isApproximate)->toBeTrue()
        ->and($monthly->breakdown)->toHaveCount(2)
    ;
});

test('passes the archived flag through to the repository', function (): void {
    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('findForHomepage')
        ->with(true)
        ->willReturn([])
    ;

    $runner = new FindSubscriptionsForHomepageRunner($repository, homepageTotaller());
    $listing = $runner(new FindSubscriptionsForHomepageQuery(includeArchived: true));

    expect($listing->groups)->toBe([])
        ->and($listing->subscriptions)->toBe([])
    ;
});
