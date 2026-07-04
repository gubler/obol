<?php

// ABOUTME: Unit tests for CreatePaymentHandler verifying payment recording via Subscription entity.
// ABOUTME: Tests that handler finds subscription and calls recordPayment; the command bus commits the change.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Entity\Subscription;
use App\Enum\PaymentType;
use App\Message\Command\Payment\CreatePaymentCommand;
use App\Message\Command\Payment\CreatePaymentHandler;
use App\Repository\SubscriptionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreatePaymentHandlerTest extends TestCase
{
    public function testHandlerRecordsPaymentOnSubscription(): void
    {
        $ulid = new Ulid();
        $paidDate = new \DateTimeImmutable('2025-01-15');

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())
            ->method('recordPayment')
            ->with($paidDate, PaymentType::Verified, 1500)
        ;
        $subscription->expects(self::never())->method('automatePayments');

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn($subscription)
        ;

        $handler = new CreatePaymentHandler($repository);
        $handler(new CreatePaymentCommand(
            ownerUserId: new Ulid(),
            subscriptionId: $ulid,
            amount: 1500,
            paidDate: $paidDate,
        ));
    }

    public function testHandlerResumesAutomatedGenerationWhenRestartIsRequested(): void
    {
        $ulid = new Ulid();
        $paidDate = new \DateTimeImmutable('2025-01-15');
        $nextRenewal = new \DateTimeImmutable('2025-03-01');

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())
            ->method('recordPayment')
            ->with($paidDate, PaymentType::Verified, 1500)
        ;
        $subscription->expects(self::once())
            ->method('automatePayments')
            ->with($nextRenewal)
        ;

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn($subscription);

        $handler = new CreatePaymentHandler($repository);
        $handler(new CreatePaymentCommand(
            ownerUserId: new Ulid(),
            subscriptionId: $ulid,
            amount: 1500,
            paidDate: $paidDate,
            restartPaymentGeneration: true,
            nextRenewal: $nextRenewal,
        ));
    }

    public function testHandlerThrowsWhenSubscriptionNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn(null)
        ;

        $handler = new CreatePaymentHandler($repository);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new CreatePaymentCommand(
            ownerUserId: new Ulid(),
            subscriptionId: $ulid,
            amount: 1500,
            paidDate: new \DateTimeImmutable(),
        ));
    }
}
