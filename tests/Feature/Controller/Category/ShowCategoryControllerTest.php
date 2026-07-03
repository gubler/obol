<?php

// ABOUTME: Feature tests for ShowCategoryController verifying category detail page functionality.
// ABOUTME: Tests ensure proper display of category details and subscriptions with 404 handling.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\TranslationAssertions;
use Symfony\Component\Uid\Ulid;

final class ShowCategoryControllerTest extends AuthenticatedTestCase
{
    use TranslationAssertions;

    public function testShowsCategoryDetails(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $categoryId = $category->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Entertainment');
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'category show');
    }

    public function testShowsTheCategoryColorAndIconBadge(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Streaming', 'color' => TileColor::Teal, 'icon' => CategoryIcon::Film]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $category->id);

        self::assertResponseIsSuccessful();
        $badge = $client->getCrawler()->filter('h1 .category-badge');
        self::assertCount(1, $badge);
        self::assertStringContainsString('bg-teal-600', (string) $badge->attr('class'));
        self::assertCount(1, $badge->filter('svg'));
    }

    public function testDisplaysSubscriptionsInCategory(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Software']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Spotify']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'GitHub']);

        $categoryId = $category->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Netflix');
        self::assertSelectorTextContains(selector: 'body', text: 'Spotify');
        self::assertSelectorTextContains(selector: 'body', text: 'GitHub');
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'category show with subscriptions');
    }

    public function testShowsCategoryDetailsSection(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h2', text: 'Category Details');
    }

    public function testShowsBackToListLink(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/categories"]');
    }

    public function testReturns404ForNonExistentCategory(): void
    {
        $client = $this->authenticatedClient();

        $nonExistentId = new Ulid();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $nonExistentId);

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testShowsEmptyStateWhenCategoryHasNoSubscriptions(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Empty Category']);
        $categoryId = $category->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $categoryId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'No subscriptions in this category');
    }
}
