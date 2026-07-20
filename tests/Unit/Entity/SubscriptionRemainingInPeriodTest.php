<?php

// ABOUTME: Unit tests for Subscription::remainingInPeriod - what is still owed by a period boundary.
// ABOUTME: Projects nextRenewal forward by the billing interval (by multiples, never drifting); payments are never consulted.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class SubscriptionRemainingInPeriodTest extends TestCase
{
    public function testSumsCostForEachRenewalUpToAndIncludingThePeriodEnd(): void
    {
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-06-01');

        // Only the June 1 renewal falls within June.
        self::assertSame(10000, $subscription->remainingInPeriod(CalendarDate::for(2026, 6, 30))->minorAmount);
        // June, July, August renewals fall on or before Aug 31.
        self::assertSame(30000, $subscription->remainingInPeriod(CalendarDate::for(2026, 8, 31))->minorAmount);
    }

    public function testCountsOverdueRenewalsFromANextRenewalAlreadyInThePast(): void
    {
        // $100/mo unpaid since April: nextRenewal is the next unpaid (April 1). By end of June: Apr, May, Jun.
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-04-01');

        self::assertSame(30000, $subscription->remainingInPeriod(CalendarDate::for(2026, 6, 30))->minorAmount);
    }

    public function testIsZeroInTheSubscriptionCurrencyWhenTheNextRenewalIsAfterThePeriodEnd(): void
    {
        // The money bug at the source: a bill due Aug 1 must not be counted in the July period. With the
        // renewal and the boundary both calendar dates, there is no zoned instant to push Aug 1 into July.
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-08-01');

        $remaining = $subscription->remainingInPeriod(CalendarDate::for(2026, 7, 31));
        self::assertSame(0, $remaining->minorAmount);
        self::assertSame(Currency::USD, $remaining->currency);
    }

    public function testAdvancesByTheFullBillingIntervalNotASinglePeriod(): void
    {
        // Every three months from Jan 1: renewals Jan, Apr, Jul, Oct fall within the year.
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 3, '2026-01-01');

        self::assertSame(40000, $subscription->remainingInPeriod(CalendarDate::for(2026, 12, 31))->minorAmount);
    }

    public function testCountsEveryMonthFromAThirtyFirstAnchorWithoutSkippingFebruary(): void
    {
        // A monthly bill anchored on the 31st once drifted (Jan 31 + P1M landed on Mar 3, skipping Feb).
        // Projecting by multiples off renewalDay=31 lands on every month's clamped day: 12 renewals, not 11.
        $subscription = $this->makeRemainingSubscription(5000, PaymentPeriod::Month, 1, '2026-01-31');

        self::assertSame(12 * 5000, $subscription->remainingInPeriod(CalendarDate::for(2026, 12, 31))->minorAmount);
    }

    private function makeRemainingSubscription(int $costMinor, PaymentPeriod $period, int $count, string $nextRenewal): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: new Category(owner: new User(email: 'owner@example.com'), name: 'Test'),
            name: 'Test',
            nextRenewal: CalendarDate::fromString($nextRenewal),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money($costMinor, Currency::USD),
            now: new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')),
        );
    }
}
