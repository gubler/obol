<?php

// ABOUTME: Unit tests for FindRemainingInPeriodRunner - what is still owed by week/month/year boundaries.
// ABOUTME: Projects nextRenewal forward against calendar period ends; a MockClock fixes "now".

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\FindRemainingInPeriodQuery;
use App\Message\Query\Report\FindRemainingInPeriodRunner;
use App\Message\Query\Report\RemainingInPeriod;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\PeriodBoundaries;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class FindRemainingInPeriodRunnerTest extends TestCase
{
    private static function remainingSubscription(int $costMinor, string $nextRenewal, Currency $currency = Currency::USD, PaymentPeriod $period = PaymentPeriod::Month, int $count = 1): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: new Category(owner: new User(email: 'owner@example.com'), name: 'Test'),
            name: 'Test',
            nextRenewal: new \DateTimeImmutable($nextRenewal),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money($costMinor, $currency),
        );
    }

    /**
     * @param list<Subscription>   $subscriptions
     * @param array<string, float> $rates
     */
    private function runRemaining(array $subscriptions, string $now, array $rates = []): RemainingInPeriod
    {
        $repository = self::createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('findActiveForOwner')->willReturn($subscriptions);

        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository));

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')->willReturn(new User(email: 'owner@example.com'));

        $runner = new FindRemainingInPeriodRunner($repository, new PeriodBoundaries(0), $totaller, $userRepository, new MockClock(new \DateTimeImmutable($now)));

        return $runner(new FindRemainingInPeriodQuery(ownerUserId: new Ulid()));
    }

    public function testProjectsRenewalsAgainstTheWeekMonthAndYearBoundaries(): void
    {
        // $100/mo, next unpaid renewal June 1; "now" is mid-June.
        $remaining = $this->runRemaining([self::remainingSubscription(10000, '2026-06-01')], now: '2026-06-15');

        self::assertInstanceOf(RemainingInPeriod::class, $remaining);
        self::assertSame(10000, $remaining->weekly->converted->minorAmount);    // just June 1
        self::assertSame(10000, $remaining->monthly->converted->minorAmount);   // just June 1
        self::assertSame(70000, $remaining->yearly->converted->minorAmount);    // June..December = 7
        self::assertFalse($remaining->yearly->isApproximate);
    }

    public function testIncludesOverdueRenewalsTheUnpaidSinceAprilExampleReads300InJune(): void
    {
        $remaining = $this->runRemaining([self::remainingSubscription(10000, '2026-04-01')], now: '2026-06-15');

        // April, May, June all fall on or before end of June.
        self::assertSame(30000, $remaining->monthly->converted->minorAmount);
    }

    public function testConvertsAMultiCurrencyRemainingTotalWithANativeBreakdown(): void
    {
        $remaining = $this->runRemaining(
            [
                self::remainingSubscription(10000, '2026-06-01'),                       // 100 USD due in June
                self::remainingSubscription(5000, '2026-06-01', Currency::EUR),         // 50 EUR -> 54 USD
            ],
            now: '2026-06-15',
            rates: ['EUR' => 1.0, 'USD' => 1.08],
        );

        self::assertSame(15400, $remaining->monthly->converted->minorAmount);   // 10000 USD + 5400 USD
        self::assertTrue($remaining->monthly->isApproximate);
        self::assertCount(2, $remaining->monthly->breakdown);
    }

    public function testIsZeroAcrossAllPeriodsWhenEveryRenewalIsBeyondTheYear(): void
    {
        $remaining = $this->runRemaining([self::remainingSubscription(10000, '2027-03-01')], now: '2026-06-15');

        self::assertSame(0, $remaining->weekly->converted->minorAmount);
        self::assertSame(0, $remaining->monthly->converted->minorAmount);
        self::assertSame(0, $remaining->yearly->converted->minorAmount);
        self::assertSame([], $remaining->yearly->breakdown);
    }
}
