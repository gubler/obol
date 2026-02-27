<?php

// ABOUTME: Feature tests for DeleteSubscriptionController verifying subscription deletion functionality.
// ABOUTME: Tests ensure proper deletion, 404 handling, and flash messages.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('delete request with valid id deletes subscription', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $subscriptionId = $subscription->id;

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscriptionId . '/delete');

    $this->assertResponseRedirects(expectedLocation: '/');

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);

    $entityManager->clear();
    $deletedSubscription = $repository->find($subscriptionId);

    expect($deletedSubscription)->toBeNull();
});

test('delete request with valid id shows success flash message', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Spotify',
    ]);

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/delete');
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-success', text: 'Subscription deleted successfully');
});

test('delete request with invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/delete');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});

test('only accepts post method', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/delete');

    $this->assertResponseStatusCodeSame(expectedCode: 405);
});

test('delete reduces subscription count', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $initialCount = SubscriptionFactory::count();

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/delete');

    $finalCount = SubscriptionFactory::count();

    expect($finalCount)->toBe($initialCount - 1);
});
