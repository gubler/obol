<?php

// ABOUTME: Integration tests for complete Category CRUD workflows end-to-end.
// ABOUTME: Tests verify create -> edit -> delete sequences with real data and no mocks.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Category;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class CategoryCrudWorkflowTest extends WebTestCase
{
    public function testCompleteCreateEditDeleteWorkflow(): void
    {
        $client = self::createClient();

        // Create
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = 'Workflow Test Category';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/categories');
        $client->followRedirect();

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $category = $repository->findOneBy(criteria: ['name' => 'Workflow Test Category']);
        self::assertNotNull($category);
        $categoryId = $category->id;

        // Edit
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/' . $categoryId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_category[name]'] = 'Updated Workflow Category';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/categories/' . $categoryId);
        $client->followRedirect();

        $entityManager->clear();
        $updatedCategory = $repository->find($categoryId);
        self::assertNotNull($updatedCategory);
        self::assertSame('Updated Workflow Category', $updatedCategory->name);

        // Delete
        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/categories/' . $categoryId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/categories');

        $entityManager->clear();
        $deletedCategory = $repository->find($categoryId);
        self::assertNull($deletedCategory);
    }

    public function testCannotDeleteCategoryWithSubscriptionsThenDeleteAfterRemovingSubscriptions(): void
    {
        $client = self::createClient();

        $category = CategoryFactory::createOne(['name' => 'Category With Sub']);
        SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);
        $categoryId = $category->id;

        // Try to delete the category. This should fail
        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/categories/' . $categoryId . '/delete');
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
        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/categories/' . $categoryId . '/delete');
        self::assertResponseRedirects(expectedLocation: '/categories');
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
        $client = self::createClient();

        $categories = ['Zebra', 'Alpha', 'Beta'];

        foreach ($categories as $name) {
            $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');
            $form = $crawler->selectButton(value: 'Save')->form();
            $form['create_category[name]'] = $name;
            $client->submit(form: $form);
            $client->followRedirect();
        }

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories');

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
