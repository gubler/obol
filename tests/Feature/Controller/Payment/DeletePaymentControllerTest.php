<?php

// ABOUTME: Feature tests for DeletePaymentController verifying payment deletion.
// ABOUTME: Tests deletion, flash message, 404 handling, and POST-only access.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('deletes payment', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);
    $payment = PaymentFactory::createOne([
        'subscription' => $subscription,
        'amount' => 1599,
    ]);

    $client->request('POST', '/payments/' . $payment->id . '/delete');

    $this->assertResponseRedirects('/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    $repository = $entityManager->getRepository(Subscription::class);
    $entityManager->clear();

    $updatedSubscription = $repository->find($subscription->id);
    expect($updatedSubscription)->not->toBeNull();
    expect($updatedSubscription->payments)->toHaveCount(0);
});

test('shows success flash message', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);
    $payment = PaymentFactory::createOne([
        'subscription' => $subscription,
        'amount' => 1599,
    ]);

    $client->request('POST', '/payments/' . $payment->id . '/delete');
    $client->followRedirect();

    $this->assertSelectorTextContains('.flash-success', 'Payment deleted successfully');
});

test('returns 404 for invalid payment id', function (): void {
    $client = $this->createClient();

    $client->request('POST', '/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/delete');

    $this->assertResponseStatusCodeSame(404);
});

test('only accepts post method', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);
    $payment = PaymentFactory::createOne([
        'subscription' => $subscription,
        'amount' => 1599,
    ]);

    $client->request('GET', '/payments/' . $payment->id . '/delete');

    $this->assertResponseStatusCodeSame(405);
});
