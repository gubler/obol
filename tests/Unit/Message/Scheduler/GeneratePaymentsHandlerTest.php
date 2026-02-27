<?php

// ABOUTME: Unit tests for GeneratePaymentsHandler verifying automatic payment generation logic.
// ABOUTME: Tests due date calculation for all period types, period count, and archived subscription skipping.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Message\Scheduler\GeneratePaymentsHandler;
use App\Message\Scheduler\GeneratePaymentsMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

function createCategory(): Category
{
    return new Category(name: 'Test Category');
}

test('generates payment for monthly subscription past due', function (): void {
    $category = createCategory();
    $subscription = new Subscription(
        category: $category,
        name: 'Netflix',
        lastPaidDate: new DateTimeImmutable('-35 days'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1599,
    );

    $initialCount = count($subscription->payments);

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')
        ->with(['archived' => false])
        ->willReturn([$subscription])
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    expect(count($subscription->payments))->toBeGreaterThan($initialCount);
});

test('skips monthly subscription not yet due', function (): void {
    $category = createCategory();
    $subscription = new Subscription(
        category: $category,
        name: 'Netflix',
        lastPaidDate: new DateTimeImmutable('-10 days'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1599,
    );

    $initialCount = count($subscription->payments);

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')
        ->with(['archived' => false])
        ->willReturn([$subscription])
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    expect($subscription->payments)->toHaveCount($initialCount);
});

test('generates payment for weekly subscription past due', function (): void {
    $category = createCategory();
    $subscription = new Subscription(
        category: $category,
        name: 'Weekly Sub',
        lastPaidDate: new DateTimeImmutable('-8 days'),
        paymentPeriod: PaymentPeriod::Week,
        paymentPeriodCount: 1,
        cost: 500,
    );

    $initialCount = count($subscription->payments);

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')
        ->with(['archived' => false])
        ->willReturn([$subscription])
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    expect(count($subscription->payments))->toBeGreaterThan($initialCount);
});

test('generates payment for yearly subscription past due', function (): void {
    $category = createCategory();
    $subscription = new Subscription(
        category: $category,
        name: 'Annual Sub',
        lastPaidDate: new DateTimeImmutable('-13 months'),
        paymentPeriod: PaymentPeriod::Year,
        paymentPeriodCount: 1,
        cost: 9999,
    );

    $initialCount = count($subscription->payments);

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')
        ->with(['archived' => false])
        ->willReturn([$subscription])
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    expect(count($subscription->payments))->toBeGreaterThan($initialCount);
});

test('respects payment period count multiplier', function (): void {
    $category = createCategory();
    // Bi-weekly: paymentPeriodCount = 2, paymentPeriod = Week
    // Due after 14 days, paid 15 days ago → should generate
    $subscription = new Subscription(
        category: $category,
        name: 'Bi-weekly Sub',
        lastPaidDate: new DateTimeImmutable('-15 days'),
        paymentPeriod: PaymentPeriod::Week,
        paymentPeriodCount: 2,
        cost: 500,
    );

    $initialCount = count($subscription->payments);

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')
        ->with(['archived' => false])
        ->willReturn([$subscription])
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    expect(count($subscription->payments))->toBeGreaterThan($initialCount);
});

test('skips bi-weekly subscription not yet due', function (): void {
    $category = createCategory();
    // Bi-weekly: paymentPeriodCount = 2, paymentPeriod = Week
    // Due after 14 days, paid 10 days ago → should NOT generate
    $subscription = new Subscription(
        category: $category,
        name: 'Bi-weekly Sub',
        lastPaidDate: new DateTimeImmutable('-10 days'),
        paymentPeriod: PaymentPeriod::Week,
        paymentPeriodCount: 2,
        cost: 500,
    );

    $initialCount = count($subscription->payments);

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')
        ->with(['archived' => false])
        ->willReturn([$subscription])
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());

    expect($subscription->payments)->toHaveCount($initialCount);
});

test('does not generate when no subscriptions exist', function (): void {
    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')
        ->with(['archived' => false])
        ->willReturn([])
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    $handler = new GeneratePaymentsHandler($repository, $entityManager);
    $handler(new GeneratePaymentsMessage());
});
