<?php

// ABOUTME: Feature tests for ListCategoriesController verifying category listing functionality.
// ABOUTME: Tests ensure proper rendering of categories index page with subscription counts.

declare(strict_types=1);

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;

test('index page renders successfully', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/categories');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'Categories');
});

test('shows empty state when no categories exist', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/categories');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: '.empty-state');
    $this->assertSelectorTextContains(selector: '.empty-state', text: 'No categories found');
});

test('displays list of categories', function (): void {
    $client = $this->createClient();

    CategoryFactory::createOne(['name' => 'Entertainment']);
    CategoryFactory::createOne(['name' => 'Software']);
    CategoryFactory::createOne(['name' => 'Utilities']);

    $client->request(method: 'GET', uri: '/categories');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'body', text: 'Entertainment');
    $this->assertSelectorTextContains(selector: 'body', text: 'Software');
    $this->assertSelectorTextContains(selector: 'body', text: 'Utilities');
});

test('displays subscription counts for categories', function (): void {
    $client = $this->createClient();

    $entertainment = CategoryFactory::createOne(['name' => 'Entertainment']);
    $software = CategoryFactory::createOne(['name' => 'Software']);

    SubscriptionFactory::createMany(3, ['category' => $entertainment]);
    SubscriptionFactory::createMany(5, ['category' => $software]);

    \Zenstruck\Foundry\Persistence\refresh($entertainment);
    \Zenstruck\Foundry\Persistence\refresh($software);

    $client->request(method: 'GET', uri: '/categories');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'body', text: '3');
    $this->assertSelectorTextContains(selector: 'body', text: '5');
});

test('shows new category button', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/categories');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/categories/new"]');
    $this->assertSelectorTextContains(selector: 'a[href="/categories/new"]', text: 'New Category');
});

test('shows view links for each category', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/categories/' . $categoryId . '"]');
});
