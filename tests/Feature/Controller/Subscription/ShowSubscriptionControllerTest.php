<?php

// ABOUTME: Feature tests for ShowSubscriptionController verifying subscription detail display.
// ABOUTME: Tests ensure proper rendering of subscription details, and 404 handling.

declare(strict_types=1);

use App\Enum\Currency;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;

test('shows subscription basic response', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix Premium',
        'cost' => new Money(1999, Currency::USD),
        'description' => 'Streaming service',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
});

test('shows edit link', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/subscriptions/' . $subscription->id . '/edit"]');
});

test('shows delete button', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'form[action="/subscriptions/' . $subscription->id . '/delete"]');
});

test('shows back to list link', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/"]');
});

test('invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});

test('renders without errors', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => new Money(1599, Currency::USD),
        'description' => 'Test description',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    // Template should render without errors
    $content = $client->getResponse()->getContent();
    expect($content)->not->toBeFalse();
    expect($content)->toContain('Netflix');
});

test('shows a manual-payments badge for a manual subscription', function (): void {
    $client = $this->createClient();
    $subscription = SubscriptionFactory::new()->manual()->create(['name' => 'Netflix']);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: '.manual-payments-badge');
});

test('shows no manual-payments badge for an automated subscription', function (): void {
    $client = $this->createClient();
    $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorNotExists(selector: '.manual-payments-badge');
});

test('offers the delete action only on the latest payment', function (): void {
    $client = $this->createClient();
    $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);
    PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => new DateTimeImmutable('2024-01-01')]);
    PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => new DateTimeImmutable('2024-02-01')]);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    // Two payments listed, but only the latest one is deletable.
    expect($crawler->filter('.payment-delete-form')->count())->toBe(1);
});
