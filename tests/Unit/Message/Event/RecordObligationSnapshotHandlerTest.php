<?php

// ABOUTME: Unit tests for RecordObligationSnapshotHandler - the event-driven obligation recorder.
// ABOUTME: Sums active subscriptions' monthlyCost by currency and records a row only when it changes.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\ObligationSnapshot;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Event\RecordObligationSnapshotHandler;
use App\Message\Event\SubscriptionsChanged;
use App\Repository\ObligationSnapshotRepository;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

function makeSubscriptionCosting(Money $cost, PaymentPeriod $period, int $count): Subscription
{
    return new Subscription(
        category: new Category(name: 'Test Category'),
        name: 'Test',
        nextRenewal: new DateTimeImmutable('2020-01-01'),
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: $cost,
    );
}

test('records a snapshot summing monthly-normalized cost by currency when none exists yet', function (): void {
    $subscriptions = [
        makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1),   // 4000 USD/month
        makeSubscriptionCosting(new Money(12000, Currency::USD), PaymentPeriod::Year, 1),    // 1000 USD/month
        makeSubscriptionCosting(new Money(3000, Currency::EUR), PaymentPeriod::Month, 1),    // 3000 EUR/month
    ];

    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->method('findBy')->with(['archived' => false])->willReturn($subscriptions);
    $snapshotRepository = $this->createMock(ObligationSnapshotRepository::class);
    $snapshotRepository->method('findLatest')->willReturn(null);

    $captured = null;
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('persist')
        ->willReturnCallback(function (object $entity) use (&$captured): void {
            $captured = $entity;
        })
    ;
    // The event bus owns the transaction (doctrine_transaction middleware); the handler never flushes.
    $entityManager->expects($this->never())->method('flush');

    $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, $this->createMock(LoggerInterface::class));
    $handler(new SubscriptionsChanged());

    expect($captured)->toBeInstanceOf(ObligationSnapshot::class);
    /* @var ObligationSnapshot $captured */
    expect($captured->obligationsByCurrency)->toEqual(['USD' => 5000, 'EUR' => 3000]);
});

test('records a new snapshot when the obligation differs from the latest', function (): void {
    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->method('findBy')->with(['archived' => false])
        ->willReturn([makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1)])
    ;
    $snapshotRepository = $this->createMock(ObligationSnapshotRepository::class);
    $snapshotRepository->method('findLatest')->willReturn(new ObligationSnapshot(['USD' => 3000]));

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('persist');

    $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, $this->createMock(LoggerInterface::class));
    $handler(new SubscriptionsChanged());
});

test('records nothing when the obligation equals the latest snapshot', function (): void {
    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->method('findBy')->with(['archived' => false])->willReturn([
        makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1),
        makeSubscriptionCosting(new Money(3000, Currency::EUR), PaymentPeriod::Month, 1),
    ]);
    $snapshotRepository = $this->createMock(ObligationSnapshotRepository::class);
    // Same totals, different key order - equality must be order-insensitive.
    $snapshotRepository->method('findLatest')->willReturn(new ObligationSnapshot(['EUR' => 3000, 'USD' => 4000]));

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->never())->method('persist');

    $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, $this->createMock(LoggerInterface::class));
    $handler(new SubscriptionsChanged());
});

test('logs and swallows a failure so it never breaks the subscription edit that triggered it', function (): void {
    $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
    $subscriptionRepository->method('findBy')->willThrowException(new RuntimeException('db is down'));
    $snapshotRepository = $this->createMock(ObligationSnapshotRepository::class);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->never())->method('persist');

    $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, $logger);
    $handler(new SubscriptionsChanged()); // must not throw
});
