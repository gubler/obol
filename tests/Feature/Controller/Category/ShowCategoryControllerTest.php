<?php

// ABOUTME: Feature tests for ShowCategoryController verifying category detail page functionality.
// ABOUTME: Tests ensure proper display of category details and subscriptions with 404 handling.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class ShowCategoryControllerTest extends WebTestCase
{
    public function testShowsCategoryDetails(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Entertainment');
    }

    public function testDisplaysSubscriptionsInCategory(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Software']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Spotify']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'GitHub']);

        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Netflix');
        self::assertSelectorTextContains(selector: 'body', text: 'Spotify');
        self::assertSelectorTextContains(selector: 'body', text: 'GitHub');
    }

    public function testShowsCategoryDetailsSection(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h2', text: 'Category Details');
    }

    public function testShowsBackToListLink(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/categories"]');
    }

    public function testReturns404ForNonExistentCategory(): void
    {
        $client = static::createClient();

        $nonExistentId = new Ulid();

        $client->request(method: 'GET', uri: '/categories/' . $nonExistentId);

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testShowsEmptyStateWhenCategoryHasNoSubscriptions(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Empty Category']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'No subscriptions in this category');
    }
}
