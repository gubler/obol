<?php

// ABOUTME: Feature tests for UnarchiveSubscriptionController verifying subscription unarchiving.
// ABOUTME: Tests ensure proper unarchiving, 404 handling, POST-only access, and flash messages.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('unarchive request unarchives subscription', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new([
        'category' => $category,
        'name' => 'Netflix',
    ])->archived()->create();

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/unarchive');

    $this->assertResponseRedirects('/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $unarchivedSubscription = $repository->find($subscription->id);
    expect($unarchivedSubscription)->not->toBeNull();
    expect($unarchivedSubscription->archived)->toBeFalse();
});

test('unarchive request shows success flash message', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new([
        'category' => $category,
        'name' => 'Spotify',
    ])->archived()->create();

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/unarchive');
    $client->followRedirect();

    $this->assertSelectorTextContains('.flash-success', 'Subscription unarchived successfully');
});

test('unarchive request with invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/unarchive');

    $this->assertResponseStatusCodeSame(404);
});

test('only accepts post method', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new([
        'category' => $category,
        'name' => 'Netflix',
    ])->archived()->create();

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/unarchive');

    $this->assertResponseStatusCodeSame(405);
});

test('unarchive creates subscription event', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new([
        'category' => $category,
        'name' => 'Netflix',
    ])->archived()->create();

    $initialEventCount = count($subscription->subscriptionEvents);

    $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/unarchive');

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $unarchivedSubscription = $repository->find($subscription->id);
    expect($unarchivedSubscription)->not->toBeNull();
    expect(count($unarchivedSubscription->subscriptionEvents))->toBeGreaterThan($initialEventCount);
});
