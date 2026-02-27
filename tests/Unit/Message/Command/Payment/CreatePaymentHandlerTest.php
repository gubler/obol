<?php

// ABOUTME: Unit tests for CreatePaymentHandler verifying payment recording via Subscription entity.
// ABOUTME: Tests that handler finds subscription, calls recordPayment, and flushes entity manager.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Enum\PaymentType;
use App\Message\Command\Payment\CreatePaymentCommand;
use App\Message\Command\Payment\CreatePaymentHandler;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler records payment on subscription', function (): void {
    $ulid = new Ulid();
    $paidDate = new DateTimeImmutable('2025-01-15');

    $subscription = $this->createMock(Subscription::class);
    $subscription->expects($this->once())
        ->method('recordPayment')
        ->with($paidDate, PaymentType::Verified, 1500)
    ;

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($subscription)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    $handler = new CreatePaymentHandler($repository, $entityManager);
    $handler(new CreatePaymentCommand(
        subscriptionId: $ulid->toRfc4122(),
        amount: 1500,
        paidDate: $paidDate,
    ));
});

test('handler throws when subscription not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new CreatePaymentHandler($repository, $entityManager);

    $handler(new CreatePaymentCommand(
        subscriptionId: $ulid->toRfc4122(),
        amount: 1500,
        paidDate: new DateTimeImmutable(),
    ));
})->throws(InvalidArgumentException::class);
