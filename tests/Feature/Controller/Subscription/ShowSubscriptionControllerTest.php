<?php

// ABOUTME: Feature tests for ShowSubscriptionController verifying subscription detail display.
// ABOUTME: Tests ensure proper rendering of subscription details, and 404 handling.

declare(strict_types=1);

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;

test('shows subscription basic response', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix Premium',
        'cost' => 1999,
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
        'cost' => 1599,
        'description' => 'Test description',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

    $this->assertResponseIsSuccessful();
    // Template should render without errors
    $content = $client->getResponse()->getContent();
    expect($content)->not->toBeFalse();
    expect($content)->toContain('Netflix');
});
