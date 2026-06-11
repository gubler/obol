<?php

// ABOUTME: Unit tests for the CategoryGroup homepage read model.
// ABOUTME: Verifies the combined savings target across a category's subscriptions as of a fixed date.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Query\Subscription\CategoryGroup;
use App\ValueObject\Money;

test('sums each subscription savings target as of the given date', function (): void {
    $category = new Category(name: 'Software');

    // 12000/yr -> 1000/mo, due 2024-01-01 (funded by Dec); seven monthly allocations are made by
    // 2023-07-01 -> 7000 each.
    $makeYearly = static fn (string $name): Subscription => new Subscription(
        category: $category,
        name: $name,
        nextRenewal: new DateTimeImmutable('2024-01-01'),
        paymentPeriod: PaymentPeriod::Year,
        paymentPeriodCount: 1,
        cost: new Money(12000, Currency::USD),
    );

    $group = new CategoryGroup(
        category: $category,
        subscriptions: [$makeYearly('JetBrains'), $makeYearly('1Password')],
        asOf: new DateTimeImmutable('2023-07-01'),
    );

    expect($group->savingsTotal()->minorAmount)->toBe(14000);
});
