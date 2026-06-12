<?php

// ABOUTME: Unit tests for UpdateSubscriptionHandler verifying subscription updates via Doctrine.
// ABOUTME: Tests happy path, subscription not found, and category not found branches.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use App\Message\Command\Subscription\UpdateSubscriptionHandler;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler updates subscription', function (): void {
    $subscriptionUlid = new Ulid();
    $categoryUlid = new Ulid();
    $nextRenewal = new DateTimeImmutable('2025-01-15');

    $subscription = $this->createMock(Subscription::class);
    $subscription->expects($this->once())->method('update');
    $subscription->expects($this->never())->method('automatePayments');

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

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->once())->method('notifyChanged');

    $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $entityManager, $notifier);
    $handler(new UpdateSubscriptionCommand(
        subscriptionId: $subscriptionUlid,
        categoryId: $categoryUlid,
        name: 'Netflix Premium',
        nextRenewal: $nextRenewal,
        description: 'Premium plan',
        link: 'https://netflix.com',
        logo: 'logo.png',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1999,
        currency: Currency::USD,
        color: TileColor::Blue,
    ));
});

test('handler resumes automated generation when restart is requested', function (): void {
    $subscriptionUlid = new Ulid();
    $categoryUlid = new Ulid();
    $nextRenewal = new DateTimeImmutable('2025-03-01');

    $subscription = $this->createMock(Subscription::class);
    $subscription->expects($this->once())->method('update');
    $subscription->expects($this->once())->method('automatePayments')->with($nextRenewal);

    $category = $this->createMock(Category::class);

    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->expects($this->once())->method('find')->willReturn($subscription);

    $categoryRepository = $this->createMock(CategoryRepository::class);
    $categoryRepository->expects($this->once())->method('find')->willReturn($category);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->once())->method('notifyChanged');

    $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $entityManager, $notifier);
    $handler(new UpdateSubscriptionCommand(
        subscriptionId: $subscriptionUlid,
        categoryId: $categoryUlid,
        name: 'Netflix Premium',
        nextRenewal: $nextRenewal,
        description: 'Premium plan',
        link: 'https://netflix.com',
        logo: 'logo.png',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1999,
        currency: Currency::USD,
        color: TileColor::Blue,
        restartPaymentGeneration: true,
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

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->never())->method('notifyChanged');

    $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $entityManager, $notifier);

    $handler(new UpdateSubscriptionCommand(
        subscriptionId: $subscriptionUlid,
        categoryId: $categoryUlid,
        name: 'Netflix',
        nextRenewal: new DateTimeImmutable(),
        description: '',
        link: '',
        logo: '',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1500,
        currency: Currency::USD,
        color: TileColor::Blue,
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

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->never())->method('notifyChanged');

    $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $entityManager, $notifier);

    $handler(new UpdateSubscriptionCommand(
        subscriptionId: $subscriptionUlid,
        categoryId: $categoryUlid,
        name: 'Netflix',
        nextRenewal: new DateTimeImmutable(),
        description: '',
        link: '',
        logo: '',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1500,
        currency: Currency::USD,
        color: TileColor::Blue,
    ));
})->throws(InvalidArgumentException::class);
