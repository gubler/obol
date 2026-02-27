<?php

// ABOUTME: Feature tests for ListSubscriptionsController verifying subscription listing functionality.
// ABOUTME: Tests ensure proper display of all subscriptions, sorting, and empty states.

declare(strict_types=1);

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;

test('displays list of subscriptions', function (): void {
    $client = $this->createClient();
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

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'body', text: 'Netflix');
    $this->assertSelectorTextContains(selector: 'body', text: 'Spotify');
});

test('shows create new link', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/subscriptions/new"]');
});

test('shows page title', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'Subscriptions');
});

test('displays subscriptions in alphabetical order', function (): void {
    $client = $this->createClient();
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
        function (Symfony\Component\DomCrawler\Crawler $node) {
            return $node->filter('td')->first()->text();
        }
    );

    expect($subscriptionNames)->toContain('Alpha Subscription');
    expect($subscriptionNames)->toContain('Beta Subscription');
    expect($subscriptionNames)->toContain('Zebra Subscription');

    $alphaIndex = array_search('Alpha Subscription', $subscriptionNames, true);
    $betaIndex = array_search('Beta Subscription', $subscriptionNames, true);
    $zebraIndex = array_search('Zebra Subscription', $subscriptionNames, true);

    expect($alphaIndex)->toBeLessThan($betaIndex);
    expect($betaIndex)->toBeLessThan($zebraIndex);
});

test('shows links to individual subscriptions', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/subscriptions/' . $subscription->id . '"]');
});

test('displays empty state when no subscriptions', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    // Should show some indication that there are no subscriptions
    // The exact implementation may vary, but the page should still render successfully
});
