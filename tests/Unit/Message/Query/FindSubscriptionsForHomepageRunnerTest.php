<?php

// ABOUTME: Unit tests for FindSubscriptionsForHomepageRunner verifying category grouping and totals.
// ABOUTME: Mocks the repository; asserts grouping order, monthly totals, and the archived pass-through.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Message\Query\Subscription\CategoryGroup;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageQuery;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageRunner;
use App\Repository\SubscriptionRepository;

function makeHomepageSubscription(Category $category, string $name, int $cost): Subscription
{
    return new Subscription(
        category: $category,
        name: $name,
        nextRenewal: new DateTimeImmutable('2024-01-01'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: $cost,
    );
}

test('groups subscriptions by category preserving order', function (): void {
    $entertainment = new Category(name: 'Entertainment');
    $utilities = new Category(name: 'Utilities');

    // Repository returns them pre-sorted by category name then subscription name.
    $subscriptions = [
        makeHomepageSubscription($entertainment, 'Netflix', 1500),
        makeHomepageSubscription($entertainment, 'Spotify', 1000),
        makeHomepageSubscription($utilities, 'Backblaze', 700),
    ];

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('findForHomepage')
        ->willReturn($subscriptions)
    ;

    $runner = new FindSubscriptionsForHomepageRunner($repository);
    $groups = $runner(new FindSubscriptionsForHomepageQuery());

    expect($groups)->toHaveCount(2)
        ->and($groups[0])->toBeInstanceOf(CategoryGroup::class)
        ->and($groups[0]->category)->toBe($entertainment)
        ->and($groups[0]->subscriptions)->toHaveCount(2)
        ->and($groups[1]->category)->toBe($utilities)
        ->and($groups[1]->subscriptions)->toHaveCount(1)
    ;
});

test('sums each category monthly total', function (): void {
    $entertainment = new Category(name: 'Entertainment');

    $subscriptions = [
        makeHomepageSubscription($entertainment, 'Netflix', 1500),
        makeHomepageSubscription($entertainment, 'Spotify', 1000),
    ];

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findForHomepage')->willReturn($subscriptions);

    $runner = new FindSubscriptionsForHomepageRunner($repository);
    $groups = $runner(new FindSubscriptionsForHomepageQuery());

    expect($groups[0]->monthlyTotal())->toBe(2500);
});

test('passes the archived flag through to the repository', function (): void {
    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('findForHomepage')
        ->with(true)
        ->willReturn([])
    ;

    $runner = new FindSubscriptionsForHomepageRunner($repository);
    $groups = $runner(new FindSubscriptionsForHomepageQuery(includeArchived: true));

    expect($groups)->toBe([]);
});
