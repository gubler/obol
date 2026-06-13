<?php

// ABOUTME: Feature tests for the /reports page and its category drill-down.
// ABOUTME: Covers the category-composition overview (archived excluded), drill-down, and a 404 for an unknown category.

declare(strict_types=1);

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Symfony\Component\Uid\Ulid;

test('the reports overview shows a per-category composition, excluding archived subscriptions', function (): void {
    $client = $this->createClient();

    $streaming = CategoryFactory::createOne(['name' => 'Streaming']);
    $software = CategoryFactory::createOne(['name' => 'Software']);
    $defunct = CategoryFactory::createOne(['name' => 'Defunct']);

    SubscriptionFactory::createOne(['category' => $streaming, 'name' => 'Netflix', 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
    SubscriptionFactory::createOne(['category' => $software, 'name' => 'JetBrains', 'cost' => new Money(1500, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
    // Both of these are archived, so neither their category nor their cost should surface.
    SubscriptionFactory::new()->archived()->create(['category' => $streaming, 'name' => 'Old Hulu', 'cost' => new Money(9900, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
    SubscriptionFactory::new()->archived()->create(['category' => $defunct, 'name' => 'Dead App', 'cost' => new Money(5000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

    $crawler = $client->request(method: 'GET', uri: '/reports');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'canvas');                                 // the pie is rendered
    $this->assertSelectorTextContains(selector: '.report-total', text: '$55.00');    // 40 + 15, archived excluded

    $names = $crawler->filter('.report-category')->each(static fn ($node): string => $node->text());
    expect($names)->toContain('Streaming')->toContain('Software')->not->toContain('Defunct');

    // Each category links to its server-navigated drill-down (base32, as path() generates from a Ulid).
    $this->assertSelectorExists(selector: sprintf('a[href="/reports/categories/%s"]', $streaming->id->toBase32()));
});

test('a category drill-down shows that category subscriptions, excluding archived', function (): void {
    $client = $this->createClient();

    $streaming = CategoryFactory::createOne(['name' => 'Streaming']);
    SubscriptionFactory::createOne(['category' => $streaming, 'name' => 'Netflix', 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
    SubscriptionFactory::new()->archived()->create(['category' => $streaming, 'name' => 'Old Hulu', 'cost' => new Money(9900, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

    $crawler = $client->request(method: 'GET', uri: '/reports/categories/' . $streaming->id->toRfc4122());

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'canvas');
    $this->assertSelectorTextContains(selector: 'h1', text: 'Streaming');

    $names = $crawler->filter('.report-subscription')->each(static fn ($node): string => $node->text());
    expect($names)->toContain('Netflix')->not->toContain('Old Hulu');
});

test('a drill-down for an unknown category is a 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/reports/categories/' . new Ulid()->toRfc4122());

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});
