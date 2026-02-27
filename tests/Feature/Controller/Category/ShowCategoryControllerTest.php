<?php

// ABOUTME: Feature tests for ShowCategoryController verifying category detail page functionality.
// ABOUTME: Tests ensure proper display of category details and subscriptions with 404 handling.

declare(strict_types=1);

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Symfony\Component\Uid\Ulid;

test('shows category details', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'Entertainment');
});

test('displays subscriptions in category', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Software']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Spotify']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'GitHub']);

    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'body', text: 'Netflix');
    $this->assertSelectorTextContains(selector: 'body', text: 'Spotify');
    $this->assertSelectorTextContains(selector: 'body', text: 'GitHub');
});

test('shows category details section', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h2', text: 'Category Details');
});

test('shows back to list link', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/categories"]');
});

test('returns 404 for non existent category', function (): void {
    $client = $this->createClient();

    $nonExistentId = new Ulid();

    $client->request(method: 'GET', uri: '/categories/' . $nonExistentId);

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});

test('shows empty state when category has no subscriptions', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Empty Category']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'body', text: 'No subscriptions in this category');
});
