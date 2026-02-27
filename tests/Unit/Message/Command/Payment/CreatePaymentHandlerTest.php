<?php

// ABOUTME: Unit tests for CreatePaymentHandler verifying payment recording via Subscription entity.
// ABOUTME: Tests that handler finds subscription, calls recordPayment, and flushes entity manager.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Entity\Subscription;
use App\Enum\PaymentType;
use App\Message\Command\Payment\CreatePaymentCommand;
use App\Message\Command\Payment\CreatePaymentHandler;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class CreatePaymentHandlerTest extends TestCase
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

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($subscription)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $handler = new CreatePaymentHandler($repository, $entityManager);
        $handler(new CreatePaymentCommand(
            subscriptionId: $ulid->toRfc4122(),
            amount: 1500,
            paidDate: $paidDate,
        ));
    }

    public function testHandlerThrowsWhenSubscriptionNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $handler = new CreatePaymentHandler($repository, $entityManager);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new CreatePaymentCommand(
            subscriptionId: $ulid->toRfc4122(),
            amount: 1500,
            paidDate: new \DateTimeImmutable(),
        ));
    }
}
