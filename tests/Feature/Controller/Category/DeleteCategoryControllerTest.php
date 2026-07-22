<?php

// ABOUTME: Feature tests for DeleteCategoryController verifying category deletion functionality.
// ABOUTME: Tests ensure proper deletion, validation for categories with subscriptions, and flash messages.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\SameOriginPostTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class DeleteCategoryControllerTest extends AuthenticatedTestCase
{
    use SameOriginPostTrait;

    public function testDeletesCategoryWithoutSubscriptionsSuccessfully(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Empty Category']);
        $categoryId = $category->id;

        $this->postSameOrigin($client, '/app/categories/' . $categoryId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/app/categories');

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $deletedCategory = $repository->find($categoryId);

        self::assertNull($deletedCategory);
    }

    public function testDeleteSuccessShowsFlashMessage(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $this->postSameOrigin($client, '/app/categories/' . $categoryId . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Category deleted successfully');
    }

    public function testCannotDeleteCategoryWithSubscriptions(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Category With Subscriptions']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

        $categoryId = $category->id;

        $this->postSameOrigin($client, '/app/categories/' . $categoryId . '/delete');

        self::assertResponseRedirects();

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $stillExistingCategory = $repository->find($categoryId);

        self::assertNotNull($stillExistingCategory);
    }

    public function testDeleteFailureShowsErrorFlashMessage(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Category With Subscriptions']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Spotify']);

        $categoryId = $category->id;

        $this->postSameOrigin($client, '/app/categories/' . $categoryId . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-error', text: 'Cannot delete category with subscriptions');
    }

    public function testReturns404ForNonExistentCategory(): void
    {
        $client = $this->authenticatedClient();

        $nonExistentId = new Ulid();

        $this->postSameOrigin($client, '/app/categories/' . $nonExistentId . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/categories/' . $categoryId . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 405);
    }
}
