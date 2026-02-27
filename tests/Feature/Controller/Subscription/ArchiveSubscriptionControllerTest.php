<?php

// ABOUTME: Feature tests for ArchiveSubscriptionController verifying subscription archiving.
// ABOUTME: Tests ensure proper archiving, 404 handling, POST-only access, and flash messages.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('archive request archives subscription', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/archive');

    $this->assertResponseRedirects('/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $archivedSubscription = $repository->find($subscription->id);
    expect($archivedSubscription)->not->toBeNull();
    expect($archivedSubscription->archived)->toBeTrue();
});

test('archive request shows success flash message', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Spotify',
    ]);

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/archive');
    $client->followRedirect();

    $this->assertSelectorTextContains('.flash-success', 'Subscription archived successfully');
});

test('archive request with invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/archive');

    $this->assertResponseStatusCodeSame(404);
});

test('only accepts post method', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/archive');

    $this->assertResponseStatusCodeSame(405);
});

test('archive creates subscription event', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $initialEventCount = count($subscription->subscriptionEvents);

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/archive');

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $archivedSubscription = $repository->find($subscription->id);
    expect($archivedSubscription)->not->toBeNull();
    expect(count($archivedSubscription->subscriptionEvents))->toBeGreaterThan($initialEventCount);
});
