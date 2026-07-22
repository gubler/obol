<?php

// ABOUTME: Integration tests for complete Category CRUD workflows end-to-end.
// ABOUTME: Tests verify create -> edit -> delete sequences with real data and no mocks.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Category;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\SameOriginPostTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

final class CategoryCrudWorkflowTest extends AuthenticatedTestCase
{
    use SameOriginPostTrait;

    public function testCompleteCreateEditDeleteWorkflow(): void
    {
        $client = $this->authenticatedClient();

        // Create
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/categories/new');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = 'Workflow Test Category';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/app/categories');
        $client->followRedirect();

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $category = $repository->findOneBy(criteria: ['name' => 'Workflow Test Category']);
        self::assertNotNull($category);
        $categoryId = $category->id;

        // Edit
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/categories/' . $categoryId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_category[name]'] = 'Updated Workflow Category';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/app/categories/' . $categoryId);
        $client->followRedirect();

        $entityManager->clear();
        $updatedCategory = $repository->find($categoryId);
        self::assertNotNull($updatedCategory);
        self::assertSame('Updated Workflow Category', $updatedCategory->name);

        // Delete
        $this->postSameOrigin($client, '/app/categories/' . $categoryId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/app/categories');

        $entityManager->clear();
        $deletedCategory = $repository->find($categoryId);
        self::assertNull($deletedCategory);
    }

    public function testCannotDeleteCategoryWithSubscriptionsThenDeleteAfterRemovingSubscriptions(): void
    {
        $client = $this->authenticatedClient();

        $category = CategoryFactory::createOne(['name' => 'Category With Sub']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);
        $categoryId = $category->id;

        // Try to delete the category. This should fail
        $this->postSameOrigin($client, '/app/categories/' . $categoryId . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-error', text: 'Cannot delete category with subscriptions');

        // Delete the subscription via the entity manager (the Foundry proxy is
        // detached after the web request due to DAMA transaction isolation).
        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $subscriptionEntity = $entityManager->getRepository(Subscription::class)->findOneBy(['name' => 'Netflix']);
        self::assertInstanceOf(Subscription::class, $subscriptionEntity);
        $entityManager->remove($subscriptionEntity);
        $entityManager->flush();

        // Now delete should work
        $this->postSameOrigin($client, '/app/categories/' . $categoryId . '/delete');
        self::assertResponseRedirects(expectedLocation: '/app/categories');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Category deleted successfully');

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);
        $deletedCategory = $repository->find($categoryId);
        self::assertNull($deletedCategory);
    }

    public function testCreateMultipleCategoriesAndVerifyListOrder(): void
    {
        $client = $this->authenticatedClient();

        $categories = ['Zebra', 'Alpha', 'Beta'];

        foreach ($categories as $name) {
            $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/categories/new');
            $form = $crawler->selectButton(value: 'Save')->form();
            $form['create_category[name]'] = $name;
            $client->submit(form: $form);
            $client->followRedirect();
        }

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/categories');

        $categoryNames = $crawler->filter('table tbody tr td:first-child')->each(
            fn (Crawler $node): string => $node->text()
        );

        // Should be sorted alphabetically
        self::assertContains('Alpha', $categoryNames);
        self::assertContains('Beta', $categoryNames);
        self::assertContains('Zebra', $categoryNames);

        // Verify Alpha comes before Beta comes before Zebra
        $alphaIndex = array_search('Alpha', $categoryNames, true);
        $betaIndex = array_search('Beta', $categoryNames, true);
        $zebraIndex = array_search('Zebra', $categoryNames, true);

        self::assertLessThan($betaIndex, $alphaIndex);
        self::assertLessThan($zebraIndex, $betaIndex);
    }
}
