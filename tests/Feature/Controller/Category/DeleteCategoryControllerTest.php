<?php

// ABOUTME: Feature tests for DeleteCategoryController verifying category deletion functionality.
// ABOUTME: Tests ensure proper deletion, validation for categories with subscriptions, and flash messages.

declare(strict_types=1);

use App\Entity\Category;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('deletes category without subscriptions successfully', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Empty Category']);
    $categoryId = $category->id;

    $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');

    $this->assertResponseRedirects(expectedLocation: '/categories');

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Category::class);

    $deletedCategory = $repository->find($categoryId);

    expect($deletedCategory)->toBeNull();
});

test('delete success shows flash message', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-success', text: 'Category deleted successfully');
});

test('cannot delete category with subscriptions', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Category With Subscriptions']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);

    $categoryId = $category->id;

    $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');

    $this->assertResponseRedirects();

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Category::class);

    $stillExistingCategory = $repository->find($categoryId);

    expect($stillExistingCategory)->not->toBeNull();
});

test('delete failure shows error flash message', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Category With Subscriptions']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Spotify']);

    $categoryId = $category->id;

    $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-error', text: 'Cannot delete category with subscriptions');
});

test('returns 404 for non existent category', function (): void {
    $client = $this->createClient();

    $nonExistentId = new Ulid();

    $client->request(method: 'POST', uri: '/categories/' . $nonExistentId . '/delete');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});

test('only accepts post method', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/delete');

    $this->assertResponseStatusCodeSame(expectedCode: 405);
});
