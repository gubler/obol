<?php

// ABOUTME: Unit tests for DeletePaymentHandler verifying payment removal via entity manager.
// ABOUTME: Tests that handler finds payment, removes it, and flushes entity manager.

declare(strict_types=1);

use App\Entity\Payment;
use App\Message\Command\Payment\DeletePaymentCommand;
use App\Message\Command\Payment\DeletePaymentHandler;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler removes payment', function (): void {
    $ulid = new Ulid();

    $payment = $this->createMock(Payment::class);

    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($payment)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())
        ->method('remove')
        ->with($payment)
    ;
    $entityManager->expects($this->once())->method('flush');

    $handler = new DeletePaymentHandler($repository, $entityManager);
    $handler(new DeletePaymentCommand(paymentId: $ulid->toRfc4122()));
});

test('handler throws when payment not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new DeletePaymentHandler($repository, $entityManager);

    $handler(new DeletePaymentCommand(paymentId: $ulid->toRfc4122()));
})->throws(InvalidArgumentException::class);
