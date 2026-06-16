<?php

// ABOUTME: Unit tests for FindCategoryBreakdownRunner - one category's subscriptions as a composition pie.
// ABOUTME: Resolves the category (null when missing), converts each active subscription's monthly share.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryBreakdownQuery;
use App\Message\Query\Report\FindCategoryBreakdownRunner;
use App\Repository\CategoryRepository;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindCategoryBreakdownRunnerTest extends TestCase
{
    private static function breakdownSubscription(?Category $category, string $name, int $costMinor, Currency $currency = Currency::USD): Subscription
    {
        return new Subscription(
            category: $category,
            name: $name,
            nextRenewal: new \DateTimeImmutable('2026-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money($costMinor, $currency),
        );
    }

    /**
     * @param list<Subscription>   $subscriptions
     * @param array<string, float> $rates
     */
    private function runBreakdown(?Category $category, array $subscriptions = [], array $rates = []): ?Composition
    {
        $categoryId = $category?->id ?? new Ulid();

        $categoryRepository = self::createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('find')->with($categoryId)->willReturn($category);

        // A stub (not a mock): the missing-category path returns early without querying, so
        // findBy is called 0 or 1 times. willReturnMap keeps the active-in-category arg match
        // without asserting a call count (any()/with()-without-expects are deprecated in PHPUnit 13).
        $subscriptionRepository = self::createStub(SubscriptionRepository::class);
        $subscriptionRepository->method('findBy')
            ->willReturnMap([
                [['archived' => false, 'category' => $category], $subscriptions],
            ])
        ;

        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));

        $runner = new FindCategoryBreakdownRunner($categoryRepository, $subscriptionRepository, $totaller);

        return $runner(new FindCategoryBreakdownQuery($categoryId));
    }

    public function testOneSlicePerSubscriptionSortedBySizeTitledWithTheCategoryName(): void
    {
        $streaming = new Category(name: 'Streaming');

        $composition = $this->runBreakdown($streaming, [
            self::breakdownSubscription($streaming, 'Netflix', 1599),
            self::breakdownSubscription($streaming, 'Spotify', 1099),
        ]);

        self::assertInstanceOf(Composition::class, $composition);
        self::assertSame('Streaming', $composition->title);
        self::assertCount(2, $composition->slices);
        self::assertSame('Netflix', $composition->slices[0]->label);   // 1599, largest first
        self::assertSame(1599, $composition->slices[0]->converted->minorAmount);
        self::assertNull($composition->slices[0]->id);                  // leaf slice, no deeper drill-down
        self::assertSame('Spotify', $composition->slices[1]->label);
        self::assertSame(2698, $composition->total->converted->minorAmount);
    }

    public function testBuildsAnUncategorizedBreakdownTitledUncategorizedWhenCategoryIdIsNull(): void
    {
        $subscriptions = [
            self::breakdownSubscription(null, 'Orphan', 1599),
            self::breakdownSubscription(null, 'Stray', 1099),
        ];

        // No category is resolved for the uncategorized drill-down; it filters on a null category.
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('find');

        $subscriptionRepository = self::createStub(SubscriptionRepository::class);
        $subscriptionRepository->method('findBy')
            ->willReturnMap([
                [['archived' => false, 'category' => null], $subscriptions],
            ])
        ;

        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')->willReturn(null);
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));

        $runner = new FindCategoryBreakdownRunner($categoryRepository, $subscriptionRepository, $totaller);
        $composition = $runner(new FindCategoryBreakdownQuery(null));

        self::assertInstanceOf(Composition::class, $composition);
        self::assertSame('Uncategorized', $composition->title);
        self::assertCount(2, $composition->slices);
        self::assertSame('Orphan', $composition->slices[0]->label);   // 1599, largest first
        self::assertSame(2698, $composition->total->converted->minorAmount);
    }

    public function testReturnsNullWhenTheCategoryDoesNotExist(): void
    {
        self::assertNull($this->runBreakdown(null));
    }

    public function testIsAnEmptyZeroTotalPieForACategoryWithNoActiveSubscriptions(): void
    {
        $empty = new Category(name: 'Empty');

        $composition = $this->runBreakdown($empty, []);

        self::assertInstanceOf(Composition::class, $composition);
        self::assertSame('Empty', $composition->title);
        self::assertSame([], $composition->slices);
        self::assertSame(0, $composition->total->converted->minorAmount);
    }
}
