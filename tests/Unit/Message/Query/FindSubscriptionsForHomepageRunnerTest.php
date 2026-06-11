<?php

// ABOUTME: Unit tests for FindSubscriptionsForHomepageRunner verifying grouping, totals and sorting.
// ABOUTME: Mocks the repository; asserts group order, per-group and flat sort order, and archived pass-through.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\SubscriptionSort;
use App\Message\Query\Subscription\CategoryGroup;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageQuery;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageRunner;
use App\Message\Query\Subscription\HomepageListing;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;

function makeHomepageSubscription(
    Category $category,
    string $name,
    int $cost,
    PaymentPeriod $period = PaymentPeriod::Month,
    int $count = 1,
    string $renewal = '2024-01-01',
): Subscription {
    return new Subscription(
        category: $category,
        name: $name,
        nextRenewal: new DateTimeImmutable($renewal),
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: new Money($cost, Currency::USD),
    );
}

/**
 * @return list<string>
 */
function names(array $subscriptions): array
{
    return array_map(static fn (Subscription $s): string => $s->name, $subscriptions);
}

function runHomepage(array $subscriptions, FindSubscriptionsForHomepageQuery $query): HomepageListing
{
    $repository = test()->createMock(SubscriptionRepository::class);
    $repository->method('findForHomepage')->willReturn($subscriptions);

    return (new FindSubscriptionsForHomepageRunner($repository))($query);
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

    expect($listing->groups[0]->monthlyTotal()->minorAmount)->toBe(2500);
});

test('passes the archived flag through to the repository', function (): void {
    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('findForHomepage')
        ->with(true)
        ->willReturn([])
    ;

    $runner = new FindSubscriptionsForHomepageRunner($repository);
    $listing = $runner(new FindSubscriptionsForHomepageQuery(includeArchived: true));

    expect($listing->groups)->toBe([])
        ->and($listing->subscriptions)->toBe([])
    ;
});
