<?php

// ABOUTME: Unit tests for DeletePaymentHandler verifying payment removal via entity manager.
// ABOUTME: Tests that handler finds payment, removes it, and flushes entity manager.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Entity\Payment;
use App\Message\Command\Payment\DeletePaymentCommand;
use App\Message\Command\Payment\DeletePaymentHandler;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class DeletePaymentHandlerTest extends TestCase
{
    public function testHandlerRemovesPayment(): void
    {
        $ulid = new Ulid();

        $payment = $this->createMock(Payment::class);

        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($payment)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('remove')
            ->with($payment)
        ;
        $entityManager->expects(self::once())->method('flush');

        $handler = new DeletePaymentHandler($repository, $entityManager);
        $handler(new DeletePaymentCommand(paymentId: $ulid->toRfc4122()));
    }

    public function testHandlerThrowsWhenPaymentNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $handler = new DeletePaymentHandler($repository, $entityManager);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new DeletePaymentCommand(paymentId: $ulid->toRfc4122()));
    }
}
