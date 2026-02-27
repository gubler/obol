<?php

// ABOUTME: Feature tests for ListCategoriesController verifying category listing functionality.
// ABOUTME: Tests ensure proper rendering of categories index page with subscription counts.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ListCategoriesControllerTest extends WebTestCase
{
    public function testIndexPageRendersSuccessfully(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Categories');
    }

    public function testShowsEmptyStateWhenNoCategoriesExist(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '.empty-state');
        self::assertSelectorTextContains(selector: '.empty-state', text: 'No categories found');
    }

    public function testDisplaysListOfCategories(): void
    {
        $client = static::createClient();

        CategoryFactory::createOne(['name' => 'Entertainment']);
        CategoryFactory::createOne(['name' => 'Software']);
        CategoryFactory::createOne(['name' => 'Utilities']);

        $client->request(method: 'GET', uri: '/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Entertainment');
        self::assertSelectorTextContains(selector: 'body', text: 'Software');
        self::assertSelectorTextContains(selector: 'body', text: 'Utilities');
    }

    public function testDisplaysSubscriptionCountsForCategories(): void
    {
        $client = static::createClient();

        $entertainment = CategoryFactory::createOne(['name' => 'Entertainment']);
        $software = CategoryFactory::createOne(['name' => 'Software']);

        SubscriptionFactory::createMany(3, ['category' => $entertainment]);
        SubscriptionFactory::createMany(5, ['category' => $software]);

        \Zenstruck\Foundry\Persistence\refresh($entertainment);
        \Zenstruck\Foundry\Persistence\refresh($software);

        $client->request(method: 'GET', uri: '/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: '3');
        self::assertSelectorTextContains(selector: 'body', text: '5');
    }

    public function testShowsNewCategoryButton(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/categories/new"]');
        self::assertSelectorTextContains(selector: 'a[href="/categories/new"]', text: 'New Category');
    }

    public function testShowsViewLinksForEachCategory(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/categories/' . $categoryId . '"]');
    }
}
