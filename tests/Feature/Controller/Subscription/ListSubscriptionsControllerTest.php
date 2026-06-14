<?php

// ABOUTME: Feature tests for ListSubscriptionsController verifying the homepage subscription listing.
// ABOUTME: Covers the tiles/list toggle, category grouping with totals, and the archived filter.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ListSubscriptionsControllerTest extends WebTestCase
{
    public function testDisplaysListOfSubscriptions(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
        ]);

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Netflix');
        self::assertSelectorTextContains(selector: 'body', text: 'Spotify');
    }

    public function testShowsCreateNewLink(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/subscriptions/new"]');
    }

    public function testShowsPageTitle(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Subscriptions');
    }

    public function testDisplaysSubscriptionsInAlphabeticalOrderInTheListView(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Zebra Subscription',
        ]);
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Alpha Subscription',
        ]);
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Beta Subscription',
        ]);

        $crawler = $client->request(method: 'GET', uri: '/?view=list');

        $subscriptionNames = $crawler->filter('table tbody tr')->each(
            fn (Crawler $node) => $node->filter('td')->first()->text()
        );

        self::assertContains('Alpha Subscription', $subscriptionNames);
        self::assertContains('Beta Subscription', $subscriptionNames);
        self::assertContains('Zebra Subscription', $subscriptionNames);

        $alphaIndex = array_search('Alpha Subscription', $subscriptionNames, true);
        $betaIndex = array_search('Beta Subscription', $subscriptionNames, true);
        $zebraIndex = array_search('Zebra Subscription', $subscriptionNames, true);

        self::assertLessThan($betaIndex, $alphaIndex);
        self::assertLessThan($zebraIndex, $betaIndex);
    }

    public function testRendersSortControls(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

        $crawler = $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        $sortControls = $crawler->filter('.sort-controls');
        self::assertSame(1, $sortControls->count());
        self::assertStringContainsString('Name', $sortControls->text());
        self::assertStringContainsString('Renewal', $sortControls->text());
        self::assertStringContainsString('Monthly cost', $sortControls->text());
        self::assertStringContainsString('Cost', $sortControls->text());
    }

    public function testOrdersTheListViewByNextRenewalWhenSortingByRenewal(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        // Alphabetical order is the reverse of renewal order, so only a renewal sort yields Zulu, Alfa.
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Alfa',
            'nextRenewal' => new \DateTimeImmutable('+30 days'),
        ]);
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Zulu',
            'nextRenewal' => new \DateTimeImmutable('+2 days'),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/?view=list&sort=renewal');

        $names = $crawler->filter('table tbody tr')->each(
            fn (Crawler $node) => $node->filter('td')->first()->text()
        );

        self::assertLessThan(array_search('Alfa', $names, true), array_search('Zulu', $names, true));
    }

    public function testOrdersTheListViewByCostDescendingWhenSortingByCost(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Alfa', 'cost' => new Money(100, Currency::USD)]);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Zulu', 'cost' => new Money(9999, Currency::USD)]);

        $crawler = $client->request(method: 'GET', uri: '/?view=list&sort=cost');

        $names = $crawler->filter('table tbody tr')->each(
            fn (Crawler $node) => $node->filter('td')->first()->text()
        );

        self::assertLessThan(array_search('Alfa', $names, true), array_search('Zulu', $names, true));
    }

    public function testPreservesTheSortPreferenceAcrossTheViewToggleLinks(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

        $client->request(method: 'GET', uri: '/?sort=cost');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href*="view=list"][href*="sort=cost"]');
    }

    public function testShowsLinksToIndividualSubscriptions(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/subscriptions/' . $subscription->id . '"]');
    }

    public function testDisplaysEmptyStateWhenNoSubscriptions(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '.empty-state');
    }

    public function testDefaultsToTheTilesView(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '.subscription-tile');
        self::assertSelectorNotExists(selector: 'table');
    }

    public function testRendersATableInTheListView(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

        $client->request(method: 'GET', uri: '/?view=list');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'table');
        self::assertSelectorNotExists(selector: '.subscription-tile');
    }

    public function testGroupsSubscriptionsByCategoryByDefault(): void
    {
        $client = self::createClient();
        $entertainment = CategoryFactory::createOne(['name' => 'Entertainment']);
        $utilities = CategoryFactory::createOne(['name' => 'Utilities']);

        SubscriptionFactory::createOne(['category' => $entertainment, 'name' => 'Netflix']);
        SubscriptionFactory::createOne(['category' => $utilities, 'name' => 'Backblaze']);

        $crawler = $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSame(2, $crawler->filter('.category-group')->count());
        self::assertSelectorTextContains(selector: 'body', text: 'Entertainment');
        self::assertSelectorTextContains(selector: 'body', text: 'Utilities');
    }

    public function testDropsCategoryHeadersInTheUngroupedView(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

        $crawler = $client->request(method: 'GET', uri: '/?group=0');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('.category-group')->count());
        self::assertSelectorTextContains(selector: 'body', text: 'Netflix');
    }

    public function testShowsTheCombinedMonthlyTotalForACategory(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1500, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
            'cost' => new Money(1000, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        // 1500 + 1000 cents per month = $25.00.
        self::assertSelectorTextContains(selector: '.category-monthly-total', text: '$25.00');
    }

    public function testShowsASavingsTargetForACategoryThatRenewsBeyondAMonth(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Software']);

        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'JetBrains',
            'cost' => new Money(12000, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Year,
            'paymentPeriodCount' => 1,
            // Renews next month, so most of the year's cost should already be set aside.
            'nextRenewal' => (new \DateTimeImmutable())->add(new \DateInterval('P1M')),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('.category-savings-total')->count());
    }

    public function testIncludesMonthlySubscriptionsInTheSavingsTarget(): void
    {
        // A monthly bill is set aside a cycle ahead, so it counts toward the category savings total -
        // keeping it reconcilable against an external monthly budget. (Exact figures are unit-tested;
        // here we only assert the total renders, since the by-month value shifts with the calendar month.)
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1500, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            // Renews tomorrow, so the current cycle is well underway.
            'nextRenewal' => (new \DateTimeImmutable())->add(new \DateInterval('P1D')),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.category-savings-total', text: '$');
        self::assertSame(1, $crawler->filter('.category-savings-total')->count());
    }

    public function testHidesArchivedSubscriptionsByDefault(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Active Sub']);
        SubscriptionFactory::new(['category' => $category, 'name' => 'Archived Sub'])->archived()->create();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Active Sub');
        self::assertSelectorTextNotContains(selector: 'body', text: 'Archived Sub');
    }

    public function testShowsArchivedSubscriptionsWhenRequested(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::new(['category' => $category, 'name' => 'Archived Sub'])->archived()->create();

        $client->request(method: 'GET', uri: '/?archived=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Archived Sub');
    }

    public function testShowsTheRelativeRenewalAndCostOnATile(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1500, Currency::USD),
            // The extra hours keep the floored relative span clear of the day boundary.
            'nextRenewal' => new \DateTimeImmutable('+2 days +6 hours'),
        ]);

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        // KnpTimeBundle renders the renewal as a rolled-up relative span ("in 2 days").
        self::assertSelectorTextContains(selector: '.subscription-tile', text: '2 days');
        self::assertSelectorTextContains(selector: '.subscription-tile', text: '$15.00');
    }
}
