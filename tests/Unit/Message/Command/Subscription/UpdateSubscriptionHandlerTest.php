<?php

// ABOUTME: Unit tests for UpdateSubscriptionHandler verifying subscription updates via Doctrine.
// ABOUTME: Tests happy path, subscription not found, and category not found branches.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use App\Message\Command\Subscription\UpdateSubscriptionHandler;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler updates subscription', function (): void {
    $subscriptionUlid = new Ulid();
    $categoryUlid = new Ulid();
    $lastPaidDate = new DateTimeImmutable('2025-01-15');

    $subscription = $this->createMock(Subscription::class);
    $subscription->expects($this->once())->method('update');

    $category = $this->createMock(Category::class);

    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->expects($this->once())
        ->method('find')
        ->willReturn($subscription)
    ;

    $categoryRepository = $this->createMock(CategoryRepository::class);
    $categoryRepository->expects($this->once())
        ->method('find')
        ->willReturn($category)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $entityManager);
    $handler(new UpdateSubscriptionCommand(
        subscriptionId: $subscriptionUlid->toRfc4122(),
        categoryId: $categoryUlid->toRfc4122(),
        name: 'Netflix Premium',
        lastPaidDate: $lastPaidDate,
        description: 'Premium plan',
        link: 'https://netflix.com',
        logo: 'logo.png',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1999,
    ));
});

test('handler throws when subscription not found', function (): void {
    $subscriptionUlid = new Ulid();
    $categoryUlid = new Ulid();

    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $categoryRepository = $this->createMock(CategoryRepository::class);
    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $entityManager);

    $handler(new UpdateSubscriptionCommand(
        subscriptionId: $subscriptionUlid->toRfc4122(),
        categoryId: $categoryUlid->toRfc4122(),
        name: 'Netflix',
        lastPaidDate: new DateTimeImmutable(),
        description: '',
        link: '',
        logo: '',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1500,
    ));
})->throws(InvalidArgumentException::class);

test('handler throws when category not found', function (): void {
    $subscriptionUlid = new Ulid();
    $categoryUlid = new Ulid();

    $subscription = $this->createMock(Subscription::class);

    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->expects($this->once())
        ->method('find')
        ->willReturn($subscription)
    ;

    $categoryRepository = $this->createMock(CategoryRepository::class);
    $categoryRepository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $entityManager);

    $handler(new UpdateSubscriptionCommand(
        subscriptionId: $subscriptionUlid->toRfc4122(),
        categoryId: $categoryUlid->toRfc4122(),
        name: 'Netflix',
        lastPaidDate: new DateTimeImmutable(),
        description: '',
        link: '',
        logo: '',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1500,
    ));
})->throws(InvalidArgumentException::class);
