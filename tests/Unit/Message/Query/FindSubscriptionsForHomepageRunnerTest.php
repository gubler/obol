<?php

// ABOUTME: Unit tests for FindSubscriptionsForHomepageRunner verifying grouping, totals and sorting.
// ABOUTME: Mocks the repository; asserts group order, sort order, archived pass-through, and converted totals.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\SubscriptionSort;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Subscription\CategoryGroup;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageQuery;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageRunner;
use App\Message\Query\Subscription\HomepageListing;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindSubscriptionsForHomepageRunnerTest extends TestCase
{
    private static function makeHomepageSubscription(
        ?Category $category,
        string $name,
        int $cost,
        PaymentPeriod $period = PaymentPeriod::Month,
        int $count = 1,
        string $renewal = '2024-01-01',
        Currency $currency = Currency::USD,
    ): Subscription {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: $category,
            name: $name,
            nextRenewal: new \DateTimeImmutable($renewal),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money($cost, $currency),
        );
    }

    /**
     * @param array<string, float> $rates EUR-pivot rates by currency code (units per 1 EUR)
     */
    private function homepageTotaller(array $rates = []): CurrencyTotaller
    {
        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;

        return new CurrencyTotaller(new Converter($exchangeRateRepository));
    }

    private static function userRepository(): UserRepository
    {
        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')->willReturn(new User(email: 'owner@example.com'));

        return $userRepository;
    }

    /**
     * @param list<Subscription> $subscriptions
     *
     * @return list<string>
     */
    private static function names(array $subscriptions): array
    {
        return array_map(static fn (Subscription $s): string => $s->name, $subscriptions);
    }

    /**
     * @param list<Subscription>   $subscriptions
     * @param array<string, float> $rates
     */
    private function runHomepage(array $subscriptions, FindSubscriptionsForHomepageQuery $query, array $rates = []): HomepageListing
    {
        $repository = self::createStub(SubscriptionRepository::class);
        $repository->method('findForHomepageForOwner')->willReturn($subscriptions);

        return (new FindSubscriptionsForHomepageRunner($repository, $this->homepageTotaller($rates), self::userRepository()))($query);
    }

    public function testGroupsByCategoryOrderedByCategoryNameSortedByNameWithinEachGroup(): void
    {
        $alpha = new Category(name: 'Alpha');
        $beta = new Category(name: 'Beta');

        // Repository order is irrelevant - the runner imposes its own ordering.
        $subscriptions = [
            self::makeHomepageSubscription($beta, 'Pear', 2000),
            self::makeHomepageSubscription($alpha, 'Mango', 1500),
            self::makeHomepageSubscription($alpha, 'Apple', 1000),
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid()));

        self::assertInstanceOf(HomepageListing::class, $listing);
        self::assertCount(2, $listing->groups);
        self::assertInstanceOf(CategoryGroup::class, $listing->groups[0]);
        self::assertSame($alpha, $listing->groups[0]->category);
        self::assertSame(['Apple', 'Mango'], self::names($listing->groups[0]->subscriptions));
        self::assertSame($beta, $listing->groups[1]->category);
        self::assertSame(['Pear'], self::names($listing->groups[1]->subscriptions));
    }

    public function testGroupsUncategorizedSubscriptionsIntoANullGroupSortedLast(): void
    {
        $zoo = new Category(name: 'Zoo');

        $subscriptions = [
            self::makeHomepageSubscription(null, 'Orphan', 1000),
            self::makeHomepageSubscription($zoo, 'Penguin', 2000),
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid()));

        self::assertCount(2, $listing->groups);
        // The named category sorts ahead of the uncategorized bucket, which always comes last.
        self::assertSame($zoo, $listing->groups[0]->category);
        self::assertNull($listing->groups[1]->category);
        self::assertSame(['Orphan'], self::names($listing->groups[1]->subscriptions));
    }

    public function testReturnsAFlatListSortedByNameByDefault(): void
    {
        $alpha = new Category(name: 'Alpha');
        $beta = new Category(name: 'Beta');

        $subscriptions = [
            self::makeHomepageSubscription($beta, 'Pear', 2000),
            self::makeHomepageSubscription($alpha, 'Apple', 1000),
            self::makeHomepageSubscription($alpha, 'Mango', 1500),
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid()));

        self::assertSame(['Apple', 'Mango', 'Pear'], self::names($listing->subscriptions));
    }

    public function testSortsByNextRenewalAscendingAcrossGroupsAndWithinThem(): void
    {
        $alpha = new Category(name: 'Alpha');
        $beta = new Category(name: 'Beta');

        $subscriptions = [
            self::makeHomepageSubscription($alpha, 'Mango', 1500, renewal: '2024-03-01'),
            self::makeHomepageSubscription($beta, 'Pear', 2000, renewal: '2024-02-01'),
            self::makeHomepageSubscription($alpha, 'Apple', 1000, renewal: '2024-01-01'),
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid(), sort: SubscriptionSort::Renewal));

        self::assertSame(['Apple', 'Pear', 'Mango'], self::names($listing->subscriptions));
        self::assertSame(['Apple', 'Mango'], self::names($listing->groups[0]->subscriptions));
        self::assertSame(['Pear'], self::names($listing->groups[1]->subscriptions));
    }

    public function testSortsByMonthlyCostDescendingDistinctFromPerPeriodCost(): void
    {
        $alpha = new Category(name: 'Alpha');

        $subscriptions = [
            // cost 12000/yr -> 1000/mo; cost 1500/mo -> 1500/mo; cost 2000/mo -> 2000/mo
            self::makeHomepageSubscription($alpha, 'Apple', 12000, PaymentPeriod::Year),
            self::makeHomepageSubscription($alpha, 'Mango', 1500),
            self::makeHomepageSubscription($alpha, 'Pear', 2000),
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid(), sort: SubscriptionSort::MonthlyCost));

        self::assertSame(['Pear', 'Mango', 'Apple'], self::names($listing->subscriptions));
    }

    public function testSortsByPerPeriodCostDescending(): void
    {
        $alpha = new Category(name: 'Alpha');

        $subscriptions = [
            self::makeHomepageSubscription($alpha, 'Apple', 12000, PaymentPeriod::Year),
            self::makeHomepageSubscription($alpha, 'Mango', 1500),
            self::makeHomepageSubscription($alpha, 'Pear', 2000),
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid(), sort: SubscriptionSort::Cost));

        self::assertSame(['Apple', 'Pear', 'Mango'], self::names($listing->subscriptions));
    }

    public function testSumsEachCategoryMonthlyTotal(): void
    {
        $entertainment = new Category(name: 'Entertainment');

        $subscriptions = [
            self::makeHomepageSubscription($entertainment, 'Netflix', 1500),
            self::makeHomepageSubscription($entertainment, 'Spotify', 1000),
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid()));

        self::assertSame(2500, $listing->groups[0]->monthlyTotal->converted->minorAmount);
        self::assertFalse($listing->groups[0]->monthlyTotal->isApproximate);
    }

    public function testSumsEachCategorySavingsTotalAcrossItsSubscriptions(): void
    {
        $software = new Category(name: 'Software');

        // A near renewal so the savings target is non-zero; it is constant within the day, so the runner's
        // "now" matches the value computed here.
        $asOf = new \DateTimeImmutable();
        $renewal = $asOf->modify('+1 day')->format('Y-m-d');
        $perSubscription = self::makeHomepageSubscription($software, 'Solo', 1000, renewal: $renewal)->savingsTarget($asOf)->minorAmount;

        $listing = $this->runHomepage([
            self::makeHomepageSubscription($software, 'JetBrains', 1000, renewal: $renewal),
            self::makeHomepageSubscription($software, '1Password', 1000, renewal: $renewal),
        ], new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid()));

        self::assertGreaterThan(0, $perSubscription);
        self::assertSame(2 * $perSubscription, $listing->groups[0]->savingsTotal->converted->minorAmount);
    }

    public function testConvertsAMixedCurrencyCategoryToTheDisplayCurrencyWithANativeBreakdown(): void
    {
        $mixed = new Category(name: 'Mixed');

        $subscriptions = [
            self::makeHomepageSubscription($mixed, 'Dollar', 4000),                                   // 4000 USD/mo
            self::makeHomepageSubscription($mixed, 'Euro', 3000, currency: Currency::EUR),            // 3000 EUR/mo -> 3240 USD
        ];

        $listing = $this->runHomepage($subscriptions, new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid()), rates: ['EUR' => 1.0, 'USD' => 1.08]);

        $monthly = $listing->groups[0]->monthlyTotal;
        self::assertSame(7240, $monthly->converted->minorAmount);   // 4000 USD + 3240 USD
        self::assertSame(Currency::USD, $monthly->converted->currency);
        self::assertTrue($monthly->isApproximate);
        self::assertCount(2, $monthly->breakdown);
    }

    public function testPassesTheArchivedFlagThroughToTheRepository(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('findForHomepageForOwner')
            ->with(self::anything(), true)
            ->willReturn([])
        ;

        $runner = new FindSubscriptionsForHomepageRunner($repository, $this->homepageTotaller(), self::userRepository());
        $listing = $runner(new FindSubscriptionsForHomepageQuery(ownerUserId: new Ulid(), includeArchived: true));

        self::assertSame([], $listing->groups);
        self::assertSame([], $listing->subscriptions);
    }
}
