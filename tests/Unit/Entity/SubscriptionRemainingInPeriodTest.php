<?php

// ABOUTME: Unit tests for Subscription::remainingInPeriod - what is still owed by a period boundary.
// ABOUTME: Projects nextRenewal forward by the billing interval; payments are never consulted.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class SubscriptionRemainingInPeriodTest extends TestCase
{
    public function testSumsCostForEachRenewalUpToAndIncludingThePeriodEnd(): void
    {
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-06-01');

        // Only the June 1 renewal falls within June.
        self::assertSame(10000, $subscription->remainingInPeriod(new \DateTimeImmutable('2026-06-30 23:59:59'))->minorAmount);
        // June, July, August renewals fall on or before Aug 31.
        self::assertSame(30000, $subscription->remainingInPeriod(new \DateTimeImmutable('2026-08-31 23:59:59'))->minorAmount);
    }

    public function testCountsOverdueRenewalsFromANextRenewalAlreadyInThePast(): void
    {
        // $100/mo unpaid since April: nextRenewal is the next unpaid (April 1). By end of June: Apr, May, Jun.
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-04-01');

        self::assertSame(30000, $subscription->remainingInPeriod(new \DateTimeImmutable('2026-06-30 23:59:59'))->minorAmount);
    }

    public function testIsZeroInTheSubscriptionCurrencyWhenTheNextRenewalIsAfterThePeriodEnd(): void
    {
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 1, '2026-07-01');

        $remaining = $subscription->remainingInPeriod(new \DateTimeImmutable('2026-06-30 23:59:59'));
        self::assertSame(0, $remaining->minorAmount);
        self::assertSame(Currency::USD, $remaining->currency);
    }

    public function testAdvancesByTheFullBillingIntervalNotASinglePeriod(): void
    {
        // Every three months from Jan 1: renewals Jan, Apr, Jul, Oct fall within the year.
        $subscription = $this->makeRemainingSubscription(10000, PaymentPeriod::Month, 3, '2026-01-01');

        self::assertSame(40000, $subscription->remainingInPeriod(new \DateTimeImmutable('2026-12-31 23:59:59'))->minorAmount);
    }

    private function makeRemainingSubscription(int $costMinor, PaymentPeriod $period, int $count, string $nextRenewal): Subscription
    {
        return new Subscription(
            category: new Category(name: 'Test'),
            name: 'Test',
            nextRenewal: new \DateTimeImmutable($nextRenewal),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money($costMinor, Currency::USD),
        );
    }
}
