<?php

// ABOUTME: Feature test for the payment generation scheduler with real database integration.
// ABOUTME: Tests that the scheduler correctly generates payments for due subscriptions and skips others.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Message\Scheduler\GeneratePaymentsHandler;
use App\Message\Scheduler\GeneratePaymentsMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

test('generates payment for due subscription', function (): void {
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        'lastPaidDate' => new DateTimeImmutable('-35 days'),
    ]);

    $container = $this->getContainer();

    /** @var SubscriptionRepository $repository */
    $repository = $container->get(SubscriptionRepository::class);

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    $entityManager->clear();

    $repository = $entityManager->getRepository(Subscription::class);
    $updatedSubscription = $repository->find($subscription->id);
    expect($updatedSubscription)->not->toBeNull();
    expect($updatedSubscription->payments)->toHaveCount(1);
});

test('skips subscription not yet due', function (): void {
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Spotify',
        'cost' => 999,
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        'lastPaidDate' => new DateTimeImmutable('-10 days'),
    ]);

    $container = $this->getContainer();

    /** @var SubscriptionRepository $repository */
    $repository = $container->get(SubscriptionRepository::class);

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    $entityManager->clear();

    $repository = $entityManager->getRepository(Subscription::class);
    $allSubscriptions = $repository->findAll();
    foreach ($allSubscriptions as $sub) {
        expect($sub->payments)->toHaveCount(0);
    }
});

test('skips archived subscription', function (): void {
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new([
        'category' => $category,
        'name' => 'Old Service',
        'cost' => 999,
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        'lastPaidDate' => new DateTimeImmutable('-35 days'),
    ])->archived()->create();

    $container = $this->getContainer();

    /** @var SubscriptionRepository $repository */
    $repository = $container->get(SubscriptionRepository::class);

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    $entityManager->clear();

    $repository = $entityManager->getRepository(Subscription::class);
    $updatedSubscription = $repository->find($subscription->id);
    expect($updatedSubscription)->not->toBeNull();
    expect($updatedSubscription->payments)->toHaveCount(0);
});
