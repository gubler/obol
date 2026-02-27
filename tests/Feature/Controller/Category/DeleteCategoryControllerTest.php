<?php

// ABOUTME: Feature tests for DeleteCategoryController verifying category deletion functionality.
// ABOUTME: Tests ensure proper deletion, validation for categories with subscriptions, and flash messages.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class DeleteCategoryControllerTest extends WebTestCase
{
    public function testDeletesCategoryWithoutSubscriptionsSuccessfully(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Empty Category']);
        $categoryId = $category->id;

        $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/categories');

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $deletedCategory = $repository->find($categoryId);

        self::assertNull($deletedCategory);
    }

    public function testDeleteSuccessShowsFlashMessage(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Category deleted successfully');
    }

    public function testCannotDeleteCategoryWithSubscriptions(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Category With Subscriptions']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

        $categoryId = $category->id;

        $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');

        self::assertResponseRedirects();

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $stillExistingCategory = $repository->find($categoryId);

        self::assertNotNull($stillExistingCategory);
    }

    public function testDeleteFailureShowsErrorFlashMessage(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Category With Subscriptions']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Spotify']);

        $categoryId = $category->id;

        $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-error', text: 'Cannot delete category with subscriptions');
    }

    public function testReturns404ForNonExistentCategory(): void
    {
        $client = static::createClient();

        $nonExistentId = new Ulid();

        $client->request(method: 'POST', uri: '/categories/' . $nonExistentId . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 405);
    }
}
