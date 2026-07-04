<?php

// ABOUTME: Unit tests for FindTotalObligationRunner - the total-obligation query behind the homepage capstone.
// ABOUTME: Sums active subs' monthlyCost by currency, converts to the display currency, scales week/month/year.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\FindTotalObligationQuery;
use App\Message\Query\Report\FindTotalObligationRunner;
use App\Message\Query\Report\TotalObligation;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindTotalObligationRunnerTest extends TestCase
{
    private static function makeObligationSubscription(Money $cost, PaymentPeriod $period, int $count = 1): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: new Category(owner: new User(email: 'owner@example.com'), name: 'Test'),
            name: 'Test',
            nextRenewal: new \DateTimeImmutable('2020-01-01'),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: $cost,
        );
    }

    /**
     * @param list<Subscription>   $subscriptions
     * @param array<string, float> $rates         EUR-pivot rates by currency code (units per 1 EUR)
     */
    private function runTotalObligation(array $subscriptions, array $rates, string $displayCurrency = 'USD'): TotalObligation
    {
        $subscriptionRepository = self::createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findActiveForOwner')->willReturn($subscriptions);

        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')
            ->willReturn(new User(email: 'owner@example.com', displayCurrency: Currency::from($displayCurrency)))
        ;

        $runner = new FindTotalObligationRunner(
            $subscriptionRepository,
            new CurrencyTotaller(new Converter($exchangeRateRepository)),
            $userRepository,
        );

        return $runner(new FindTotalObligationQuery(ownerUserId: new Ulid()));
    }

    public function testSumsActiveSubscriptionsConvertsToTheDisplayCurrencyAndKeepsANativeBreakdown(): void
    {
        $totals = $this->runTotalObligation(
            subscriptions: [
                self::makeObligationSubscription(new Money(4000, Currency::USD), PaymentPeriod::Month),  // 4000 USD/mo
                self::makeObligationSubscription(new Money(12000, Currency::USD), PaymentPeriod::Year),   // 1000 USD/mo
                self::makeObligationSubscription(new Money(3000, Currency::EUR), PaymentPeriod::Month),   // 3000 EUR/mo -> 3240 USD
            ],
            rates: ['EUR' => 1.0, 'USD' => 1.08],
        );

        self::assertInstanceOf(TotalObligation::class, $totals);
        self::assertSame(8240, $totals->monthly->minorAmount);        // 5000 USD + 3240 USD
        self::assertSame(Currency::USD, $totals->monthly->currency);
        self::assertSame(1902, $totals->weekly->minorAmount);         // round(8240 * 12/52)
        self::assertSame(98880, $totals->yearly->minorAmount);        // 8240 * 12
        self::assertTrue($totals->isApproximate);
        self::assertInstanceOf(\DateTimeImmutable::class, $totals->asOf);

        self::assertCount(2, $totals->breakdown);
        // Key-sorted by currency code: EUR before USD.
        self::assertSame(Currency::EUR, $totals->breakdown[0]->currency);
        self::assertSame(3000, $totals->breakdown[0]->minorAmount);
        self::assertSame(Currency::USD, $totals->breakdown[1]->currency);
        self::assertSame(5000, $totals->breakdown[1]->minorAmount);
    }

    public function testScalesWeekAndYearFromTheMonthlyTotalWhenNothingNeedsConverting(): void
    {
        $totals = $this->runTotalObligation(
            subscriptions: [self::makeObligationSubscription(new Money(5800, Currency::USD), PaymentPeriod::Month)],
            rates: [],
        );

        self::assertSame(5800, $totals->monthly->minorAmount);
        self::assertSame(1338, $totals->weekly->minorAmount);    // round(5800 * 12/52)
        self::assertSame(69600, $totals->yearly->minorAmount);   // 5800 * 12
        self::assertFalse($totals->isApproximate);
        self::assertCount(1, $totals->breakdown);
    }

    public function testReportsAZeroTotalInTheDisplayCurrencyWhenThereAreNoActiveSubscriptions(): void
    {
        $totals = $this->runTotalObligation(subscriptions: [], rates: []);

        self::assertSame(0, $totals->monthly->minorAmount);
        self::assertSame(Currency::USD, $totals->monthly->currency);
        self::assertSame(0, $totals->weekly->minorAmount);
        self::assertSame(0, $totals->yearly->minorAmount);
        self::assertFalse($totals->isApproximate);
        self::assertSame([], $totals->breakdown);
    }
}
