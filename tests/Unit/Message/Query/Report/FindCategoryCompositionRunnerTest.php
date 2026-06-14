<?php

// ABOUTME: Unit tests for FindCategoryCompositionRunner - each category's share of the monthly obligation.
// ABOUTME: Groups active subscriptions by category, converts each share to the display currency, sorts by size.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryCompositionQuery;
use App\Message\Query\Report\FindCategoryCompositionRunner;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class FindCategoryCompositionRunnerTest extends TestCase
{
    private static function compositionSubscription(Category $category, int $costMinor, Currency $currency = Currency::USD, PaymentPeriod $period = PaymentPeriod::Month, int $count = 1): Subscription
    {
        return new Subscription(
            category: $category,
            name: 'Test',
            nextRenewal: new \DateTimeImmutable('2026-01-01'),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money($costMinor, $currency),
        );
    }

    /**
     * @param list<Subscription>   $subscriptions
     * @param array<string, float> $rates
     */
    private function runComposition(array $subscriptions, array $rates = [], string $displayCurrency = 'USD'): Composition
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')->with(['archived' => false])->willReturn($subscriptions);

        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider($displayCurrency));

        $runner = new FindCategoryCompositionRunner($repository, $totaller);

        return $runner(new FindCategoryCompositionQuery());
    }

    public function testOneSlicePerCategorySortedByShareDescendingWithTheOverallTotal(): void
    {
        $streaming = new Category(name: 'Streaming');
        $software = new Category(name: 'Software');

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

    public function testConvertsAMixedCurrencyCategoryShareAndKeepsTheNativeBreakdown(): void
    {
        $mixed = new Category(name: 'Mixed');

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
