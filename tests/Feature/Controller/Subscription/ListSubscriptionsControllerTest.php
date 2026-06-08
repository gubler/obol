<?php

// ABOUTME: Feature tests for ListSubscriptionsController verifying the homepage subscription listing.
// ABOUTME: Covers the tiles/list toggle, category grouping with totals, and the archived filter.

declare(strict_types=1);

use App\Enum\PaymentPeriod;
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

test('displays subscriptions in alphabetical order in the list view', function (): void {
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

    $crawler = $client->request(method: 'GET', uri: '/?view=list');

    $subscriptionNames = $crawler->filter('table tbody tr')->each(
        fn (Symfony\Component\DomCrawler\Crawler $node) => $node->filter('td')->first()->text()
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
    $this->assertSelectorExists(selector: '.empty-state');
});

test('defaults to the tiles view', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: '.subscription-tile');
    $this->assertSelectorNotExists(selector: 'table');
});

test('renders a table in the list view', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

    $client->request(method: 'GET', uri: '/?view=list');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'table');
    $this->assertSelectorNotExists(selector: '.subscription-tile');
});

test('groups subscriptions by category by default', function (): void {
    $client = $this->createClient();
    $entertainment = CategoryFactory::createOne(['name' => 'Entertainment']);
    $utilities = CategoryFactory::createOne(['name' => 'Utilities']);

    SubscriptionFactory::createOne(['category' => $entertainment, 'name' => 'Netflix']);
    SubscriptionFactory::createOne(['category' => $utilities, 'name' => 'Backblaze']);

    $crawler = $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    expect($crawler->filter('.category-group')->count())->toBe(2);
    $this->assertSelectorTextContains(selector: 'body', text: 'Entertainment');
    $this->assertSelectorTextContains(selector: 'body', text: 'Utilities');
});

test('drops category headers in the ungrouped view', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

    $crawler = $client->request(method: 'GET', uri: '/?group=0');

    $this->assertResponseIsSuccessful();
    expect($crawler->filter('.category-group')->count())->toBe(0);
    $this->assertSelectorTextContains(selector: 'body', text: 'Netflix');
});

test('shows the combined monthly total for a category', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1500,
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
    ]);
    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Spotify',
        'cost' => 1000,
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
    ]);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    // 1500 + 1000 cents per month = $25.00.
    $this->assertSelectorTextContains(selector: '.category-monthly-total', text: '$25.00');
});

test('hides archived subscriptions by default', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Active Sub']);
    SubscriptionFactory::new(['category' => $category, 'name' => 'Archived Sub'])->archived()->create();

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'body', text: 'Active Sub');
    $this->assertSelectorTextNotContains(selector: 'body', text: 'Archived Sub');
});

test('shows archived subscriptions when requested', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    SubscriptionFactory::new(['category' => $category, 'name' => 'Archived Sub'])->archived()->create();

    $client->request(method: 'GET', uri: '/?archived=1');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'body', text: 'Archived Sub');
});

test('shows the relative renewal and cost on a tile', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1500,
        // The extra hours keep the floored relative span clear of the day boundary.
        'nextRenewal' => new DateTimeImmutable('+2 days +6 hours'),
    ]);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    // KnpTimeBundle renders the renewal as a rolled-up relative span ("in 2 days").
    $this->assertSelectorTextContains(selector: '.subscription-tile', text: '2 days');
    $this->assertSelectorTextContains(selector: '.subscription-tile', text: '$15.00');
});
