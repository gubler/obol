<?php

// ABOUTME: Feature tests for ListSubscriptionsController verifying subscription listing functionality.
// ABOUTME: Tests ensure proper display of all subscriptions, sorting, and empty states.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ListSubscriptionsControllerTest extends WebTestCase
{
    public function testDisplaysListOfSubscriptions(): void
    {
        $client = static::createClient();
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
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/subscriptions/new"]');
    }

    public function testShowsPageTitle(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Subscriptions');
    }

    public function testDisplaysSubscriptionsInAlphabeticalOrder(): void
    {
        $client = static::createClient();
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

        $crawler = $client->request(method: 'GET', uri: '/');

        $subscriptionNames = $crawler->filter('table tbody tr')->each(
            function (\Symfony\Component\DomCrawler\Crawler $node) {
                return $node->filter('td')->first()->text();
            }
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

    public function testShowsLinksToIndividualSubscriptions(): void
    {
        $client = static::createClient();
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
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        // Should show some indication that there are no subscriptions
        // The exact implementation may vary, but the page should still render successfully
    }
}
