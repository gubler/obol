<?php

// ABOUTME: Feature tests for CreatePaymentController verifying payment creation via form.
// ABOUTME: Tests form display, valid submission, validation errors, and 404 for invalid subscription.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('displays create payment form', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists('form');
});

test('creates payment with valid data', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
    ]);

    $initialPaymentCount = count($subscription->payments);

    $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
    $client->submitForm('Save', [
        'create_payment[amount]' => '1599',
        'create_payment[paidDate]' => '2025-01-15',
    ]);

    $this->assertResponseRedirects('/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    $repository = $entityManager->getRepository(Subscription::class);
    $entityManager->clear();

    $updatedSubscription = $repository->find($subscription->id);
    expect($updatedSubscription)->not->toBeNull();
    expect(count($updatedSubscription->payments))->toBeGreaterThan($initialPaymentCount);
});

test('shows success flash message after creation', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
    $client->submitForm('Save', [
        'create_payment[amount]' => '1599',
        'create_payment[paidDate]' => '2025-01-15',
    ]);
    $client->followRedirect();

    $this->assertSelectorTextContains('.flash-success', 'Payment recorded successfully');
});

test('shows validation errors for invalid data', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
    $client->submitForm('Save', [
        'create_payment[amount]' => '',
        'create_payment[paidDate]' => '',
    ]);

    $this->assertResponseStatusCodeSame(422);
    $this->assertSelectorExists('.text-red-700');
});

test('returns 404 for invalid subscription id', function (): void {
    $client = $this->createClient();

    $client->request('GET', '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/payments/new');

    $this->assertResponseStatusCodeSame(404);
});
