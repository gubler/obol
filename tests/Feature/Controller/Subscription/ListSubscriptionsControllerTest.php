<?php

// ABOUTME: Feature tests for ListSubscriptionsController verifying the homepage subscription listing.
// ABOUTME: Covers the tiles/list toggle, category grouping with totals, and the archived filter.

declare(strict_types=1);

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;

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

test('renders sort controls', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

    $crawler = $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $sortControls = $crawler->filter('.sort-controls');
    expect($sortControls->count())->toBe(1)
        ->and($sortControls->text())->toContain('Name')
        ->and($sortControls->text())->toContain('Renewal')
        ->and($sortControls->text())->toContain('Monthly cost')
        ->and($sortControls->text())->toContain('Cost')
    ;
});

test('orders the list view by next renewal when sorting by renewal', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    // Alphabetical order is the reverse of renewal order, so only a renewal sort yields Zulu, Alfa.
    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Alfa',
        'nextRenewal' => new DateTimeImmutable('+30 days'),
    ]);
    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Zulu',
        'nextRenewal' => new DateTimeImmutable('+2 days'),
    ]);

    $crawler = $client->request(method: 'GET', uri: '/?view=list&sort=renewal');

    $names = $crawler->filter('table tbody tr')->each(
        fn (Symfony\Component\DomCrawler\Crawler $node) => $node->filter('td')->first()->text()
    );

    expect(array_search('Zulu', $names, true))->toBeLessThan(array_search('Alfa', $names, true));
});

test('orders the list view by cost descending when sorting by cost', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Alfa', 'cost' => new Money(100, Currency::USD)]);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Zulu', 'cost' => new Money(9999, Currency::USD)]);

    $crawler = $client->request(method: 'GET', uri: '/?view=list&sort=cost');

    $names = $crawler->filter('table tbody tr')->each(
        fn (Symfony\Component\DomCrawler\Crawler $node) => $node->filter('td')->first()->text()
    );

    expect(array_search('Zulu', $names, true))->toBeLessThan(array_search('Alfa', $names, true));
});

test('preserves the sort preference across the view toggle links', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

    $client->request(method: 'GET', uri: '/?sort=cost');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href*="view=list"][href*="sort=cost"]');
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

    $this->assertResponseIsSuccessful();
    // 1500 + 1000 cents per month = $25.00.
    $this->assertSelectorTextContains(selector: '.category-monthly-total', text: '$25.00');
});

test('shows a savings target for a category that renews beyond a month', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Software']);

    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'JetBrains',
        'cost' => new Money(12000, Currency::USD),
        'paymentPeriod' => PaymentPeriod::Year,
        'paymentPeriodCount' => 1,
        // Renews next month, so most of the year's cost should already be set aside.
        'nextRenewal' => (new DateTimeImmutable())->add(new DateInterval('P1M')),
    ]);

    $crawler = $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    expect($crawler->filter('.category-savings-total')->count())->toBe(1);
});

test('includes monthly subscriptions in the savings target', function (): void {
    // A monthly bill is set aside a cycle ahead, so it counts toward the category savings total -
    // keeping it reconcilable against an external monthly budget. (Exact figures are unit-tested;
    // here we only assert the total renders, since the by-month value shifts with the calendar month.)
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => new Money(1500, Currency::USD),
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        // Renews tomorrow, so the current cycle is well underway.
        'nextRenewal' => (new DateTimeImmutable())->add(new DateInterval('P1D')),
    ]);

    $crawler = $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: '.category-savings-total', text: '$');
    expect($crawler->filter('.category-savings-total')->count())->toBe(1);
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
        'cost' => new Money(1500, Currency::USD),
        // The extra hours keep the floored relative span clear of the day boundary.
        'nextRenewal' => new DateTimeImmutable('+2 days +6 hours'),
    ]);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    // KnpTimeBundle renders the renewal as a rolled-up relative span ("in 2 days").
    $this->assertSelectorTextContains(selector: '.subscription-tile', text: '2 days');
    $this->assertSelectorTextContains(selector: '.subscription-tile', text: '$15.00');
});
