<?php

// ABOUTME: Integration tests for complete Category CRUD workflows end-to-end.
// ABOUTME: Tests verify create -> edit -> delete sequences with real data and no mocks.

declare(strict_types=1);

use App\Entity\Category;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

test('complete create edit delete workflow', function (): void {
    $client = $this->createClient();

    // Create
    $crawler = $client->request(method: 'GET', uri: '/categories/new');
    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_category[name]'] = 'Workflow Test Category';
    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/categories');
    $client->followRedirect();

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Category::class);

    $category = $repository->findOneBy(criteria: ['name' => 'Workflow Test Category']);
    expect($category)->not->toBeNull();
    $categoryId = $category->id;

    // Edit
    $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');
    $form = $crawler->selectButton(value: 'Save')->form();
    $form['edit_category[name]'] = 'Updated Workflow Category';
    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/categories/' . $categoryId);
    $client->followRedirect();

    $entityManager->clear();
    $updatedCategory = $repository->find($categoryId);
    expect($updatedCategory)->not->toBeNull();
    expect($updatedCategory->name)->toBe('Updated Workflow Category');

    // Delete
    $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');

    $this->assertResponseRedirects(expectedLocation: '/categories');

    $entityManager->clear();
    $deletedCategory = $repository->find($categoryId);
    expect($deletedCategory)->toBeNull();
});

test('cannot delete category with subscriptions then delete after removing subscriptions', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Category With Sub']);
    SubscriptionFactory::createOne(['category' => $category, 'name' => 'Netflix']);
    $categoryId = $category->id;

    // Try to delete the category. This should fail
    $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-error', text: 'Cannot delete category with subscriptions');

    // Delete the subscription via the entity manager (the Foundry proxy is
    // detached after the web request due to DAMA transaction isolation).
    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $subscriptionEntity = $entityManager->getRepository(App\Entity\Subscription::class)->findOneBy(['name' => 'Netflix']);
    $entityManager->remove($subscriptionEntity);
    $entityManager->flush();

    // Now delete should work
    $client->request(method: 'POST', uri: '/categories/' . $categoryId . '/delete');
    $this->assertResponseRedirects(expectedLocation: '/categories');
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-success', text: 'Category deleted successfully');

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Category::class);
    $deletedCategory = $repository->find($categoryId);
    expect($deletedCategory)->toBeNull();
});

test('create multiple categories and verify list order', function (): void {
    $client = $this->createClient();

    $categories = ['Zebra', 'Alpha', 'Beta'];

    foreach ($categories as $name) {
        $crawler = $client->request(method: 'GET', uri: '/categories/new');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = $name;
        $client->submit(form: $form);
        $client->followRedirect();
    }

    $crawler = $client->request(method: 'GET', uri: '/categories');

    $categoryNames = $crawler->filter('table tbody tr td:first-child')->each(
        function (Crawler $node): string {
            return $node->text();
        }
    );

    // Should be sorted alphabetically
    expect($categoryNames)->toContain('Alpha');
    expect($categoryNames)->toContain('Beta');
    expect($categoryNames)->toContain('Zebra');

    // Verify Alpha comes before Beta comes before Zebra
    $alphaIndex = array_search('Alpha', $categoryNames, true);
    $betaIndex = array_search('Beta', $categoryNames, true);
    $zebraIndex = array_search('Zebra', $categoryNames, true);

    expect($alphaIndex)->toBeLessThan($betaIndex);
    expect($betaIndex)->toBeLessThan($zebraIndex);
});
