<?php

// ABOUTME: Unit tests for FindCategoryCompositionRunner - each category's share of the monthly obligation.
// ABOUTME: Groups active subscriptions by category, converts each share to the display currency, sorts by size.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryCompositionQuery;
use App\Message\Query\Report\FindCategoryCompositionRunner;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FindCategoryCompositionRunnerTest extends TestCase
{
    private static function compositionSubscription(?Category $category, int $costMinor, Currency $currency = Currency::USD, PaymentPeriod $period = PaymentPeriod::Month, int $count = 1): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: $category,
            name: 'Test',
            nextRenewal: CalendarDate::fromString('2026-01-01'),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money($costMinor, $currency),
            now: new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')),
        );
    }

    /**
     * @param list<Subscription>   $subscriptions
     * @param array<string, float> $rates
     */
    private function runComposition(array $subscriptions, array $rates = [], string $displayCurrency = 'USD'): Composition
    {
        $repository = self::createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('findActiveForOwner')->willReturn($subscriptions);

        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository));

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')
            ->willReturn(new User(email: 'owner@example.com', displayCurrency: Currency::from($displayCurrency)))
        ;

        $runner = new FindCategoryCompositionRunner($repository, $totaller, $userRepository, new MockClock(), self::translator());

        return $runner(new FindCategoryCompositionQuery(ownerUserId: new Ulid()));
    }

    /**
     * A translator that resolves the uncategorized-label key to its en value, so slice labels stay unchanged.
     */
    private static function translator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key): string => ['subscription.group.uncategorized' => 'Uncategorized'][$key] ?? $key,
        );

        return $translator;
    }

    public function testOneSlicePerCategorySortedByShareDescendingWithTheOverallTotal(): void
    {
        $streaming = new Category(owner: new User(email: 'owner@example.com'), name: 'Streaming');
        $software = new Category(owner: new User(email: 'owner@example.com'), name: 'Software');

        $composition = $this->runComposition([
            self::compositionSubscription($streaming, 1000),
            self::compositionSubscription($streaming, 500),
            self::compositionSubscription($software, 4000),
        ]);

        self::assertInstanceOf(Composition::class, $composition);
        self::assertCount(2, $composition->slices);
        self::assertSame('Software', $composition->slices[0]->label);   // 4000, largest share first
        self::assertSame(4000, $composition->slices[0]->converted->minorAmount);
        self::assertSame($software->id, $composition->slices[0]->id);
        self::assertSame('Streaming', $composition->slices[1]->label);  // 1500
        self::assertSame(1500, $composition->slices[1]->converted->minorAmount);
        self::assertSame(5500, $composition->total->converted->minorAmount);
        self::assertFalse($composition->total->isApproximate);
        self::assertNull($composition->title);
    }

    public function testCollectsUncategorizedSubscriptionsIntoASingleUncategorizedSlice(): void
    {
        $software = new Category(owner: new User(email: 'owner@example.com'), name: 'Software');

        $composition = $this->runComposition([
            self::compositionSubscription($software, 1000),
            self::compositionSubscription(null, 4000),
            self::compositionSubscription(null, 500),
        ]);

        self::assertCount(2, $composition->slices);
        // The 4500 uncategorized share is the largest, so it sorts first.
        self::assertSame('Uncategorized', $composition->slices[0]->label);
        self::assertSame(4500, $composition->slices[0]->converted->minorAmount);
        self::assertTrue($composition->slices[0]->uncategorized);
        self::assertNull($composition->slices[0]->id);
        self::assertSame('Software', $composition->slices[1]->label);
        self::assertFalse($composition->slices[1]->uncategorized);
    }

    public function testConvertsAMixedCurrencyCategoryShareAndKeepsTheNativeBreakdown(): void
    {
        $mixed = new Category(owner: new User(email: 'owner@example.com'), name: 'Mixed');

        $composition = $this->runComposition(
            [
                self::compositionSubscription($mixed, 10000),                   // 100 USD
                self::compositionSubscription($mixed, 5000, Currency::EUR),     // 50 EUR -> 54 USD
            ],
            rates: ['EUR' => 1.0, 'USD' => 1.08],
        );

        self::assertCount(1, $composition->slices);
        self::assertSame(15400, $composition->slices[0]->converted->minorAmount);  // 10000 + 5400
        self::assertTrue($composition->slices[0]->isApproximate);
        self::assertCount(2, $composition->slices[0]->breakdown);            // native USD + EUR
        self::assertSame(15400, $composition->total->converted->minorAmount);
        self::assertTrue($composition->total->isApproximate);
    }

    public function testIsEmptyWithAZeroTotalWhenThereAreNoActiveSubscriptions(): void
    {
        $composition = $this->runComposition([]);

        self::assertSame([], $composition->slices);
        self::assertSame(0, $composition->total->converted->minorAmount);
        self::assertSame([], $composition->total->breakdown);
    }
}
