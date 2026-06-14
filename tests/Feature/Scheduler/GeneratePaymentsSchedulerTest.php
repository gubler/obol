<?php

// ABOUTME: Feature test for the payment generation scheduler with real database integration.
// ABOUTME: Dispatches via the command bus so the doctrine_transaction middleware commits the handler's work.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Lib\Bus\CommandBus;
use App\Message\Scheduler\GeneratePaymentsMessage;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

test('generates payment for due subscription', function (): void {
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => new Money(1599, Currency::USD),
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        'nextRenewal' => new DateTimeImmutable('-35 days'),
    ]);

    $container = $this->getContainer();
    $container->get(CommandBus::class)->dispatch(new GeneratePaymentsMessage());

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    $entityManager->clear();

    $updatedSubscription = $entityManager->getRepository(Subscription::class)->find($subscription->id);
    expect($updatedSubscription)->not->toBeNull();
    expect($updatedSubscription->payments)->toHaveCount(1);
});

test('skips subscription not yet due', function (): void {
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Spotify',
        'cost' => new Money(999, Currency::USD),
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        'nextRenewal' => new DateTimeImmutable('+10 days'),
    ]);

    $container = $this->getContainer();
    $container->get(CommandBus::class)->dispatch(new GeneratePaymentsMessage());

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    $entityManager->clear();

    foreach ($entityManager->getRepository(Subscription::class)->findAll() as $sub) {
        expect($sub->payments)->toHaveCount(0);
    }
});

test('skips archived subscription', function (): void {
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new([
        'category' => $category,
        'name' => 'Old Service',
        'cost' => new Money(999, Currency::USD),
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        'nextRenewal' => new DateTimeImmutable('-35 days'),
    ])->archived()->create();

    $container = $this->getContainer();
    $container->get(CommandBus::class)->dispatch(new GeneratePaymentsMessage());

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    $entityManager->clear();

    $updatedSubscription = $entityManager->getRepository(Subscription::class)->find($subscription->id);
    expect($updatedSubscription)->not->toBeNull();
    expect($updatedSubscription->payments)->toHaveCount(0);
});
