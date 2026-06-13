<?php

// ABOUTME: Unit tests for Subscription::remainingInPeriod - what is still owed by a period boundary.
// ABOUTME: Projects nextRenewal forward by the billing interval; payments are never consulted.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\ValueObject\Money;

function makeRemainingSubscription(int $costMinor, PaymentPeriod $period, int $count, string $nextRenewal): Subscription
{
    return new Subscription(
        category: new Category(name: 'Test'),
        name: 'Test',
        nextRenewal: new DateTimeImmutable($nextRenewal),
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: new Money($costMinor, Currency::USD),
    );
}

test('sums cost for each renewal up to and including the period end', function (): void {
    $subscription = makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-06-01');

    // Only the June 1 renewal falls within June.
    expect($subscription->remainingInPeriod(new DateTimeImmutable('2026-06-30 23:59:59'))->minorAmount)->toBe(10000)
        // June, July, August renewals fall on or before Aug 31.
        ->and($subscription->remainingInPeriod(new DateTimeImmutable('2026-08-31 23:59:59'))->minorAmount)->toBe(30000)
    ;
});

test('counts overdue renewals from a next renewal already in the past', function (): void {
    // $100/mo unpaid since April: nextRenewal is the next unpaid (April 1). By end of June: Apr, May, Jun.
    $subscription = makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-04-01');

    expect($subscription->remainingInPeriod(new DateTimeImmutable('2026-06-30 23:59:59'))->minorAmount)->toBe(30000);
});

test('is zero in the subscription currency when the next renewal is after the period end', function (): void {
    $subscription = makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-07-01');

    $remaining = $subscription->remainingInPeriod(new DateTimeImmutable('2026-06-30 23:59:59'));
    expect($remaining->minorAmount)->toBe(0)
        ->and($remaining->currency)->toBe(Currency::USD)
    ;
});

test('advances by the full billing interval, not a single period', function (): void {
    // Every three months from Jan 1: renewals Jan, Apr, Jul, Oct fall within the year.
    $subscription = makeRemainingSubscription(10000, PaymentPeriod::Month, 3, '2026-01-01');

    expect($subscription->remainingInPeriod(new DateTimeImmutable('2026-12-31 23:59:59'))->minorAmount)->toBe(40000);
});
