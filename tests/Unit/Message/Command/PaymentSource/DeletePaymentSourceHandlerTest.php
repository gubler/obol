<?php

// ABOUTME: Unit tests for DeletePaymentSourceHandler verifying removal via Doctrine.
// ABOUTME: Tests happy path, not-found, and the has-subscriptions guard.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\PaymentSource;

use App\Entity\PaymentSource;
use App\Exception\PaymentSourceHasSubscriptionsException;
use App\Message\Command\PaymentSource\DeletePaymentSourceCommand;
use App\Message\Command\PaymentSource\DeletePaymentSourceHandler;
use App\Repository\PaymentSourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class DeletePaymentSourceHandlerTest extends TestCase
{
    public function testHandlerRemovesPaymentSourceWithNoSubscriptions(): void
    {
        $ulid = new Ulid();

        $source = new PaymentSource(name: 'Test');

        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($source)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('remove')
            ->with($source)
        ;
        // The command bus owns the transaction (doctrine_transaction middleware); the handler never flushes.
        $entityManager->expects(self::never())->method('flush');

        $handler = new DeletePaymentSourceHandler($repository, $entityManager);
        $handler(new DeletePaymentSourceCommand(paymentSourceId: $ulid));
    }

    public function testHandlerThrowsWhenPaymentSourceNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $entityManager = self::createStub(EntityManagerInterface::class);

        $handler = new DeletePaymentSourceHandler($repository, $entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new DeletePaymentSourceCommand(paymentSourceId: $ulid));
    }

    public function testHandlerThrowsWhenPaymentSourceHasSubscriptions(): void
    {
        $ulid = new Ulid();

        $source = new PaymentSource(name: 'Test');

        // Use reflection to add items to the private(set) subscriptions collection.
        $reflection = new \ReflectionProperty(PaymentSource::class, 'subscriptions');
        $reflection->setValue($source, new ArrayCollection(['placeholder']));

        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($source)
        ;

        $entityManager = self::createStub(EntityManagerInterface::class);

        $handler = new DeletePaymentSourceHandler($repository, $entityManager);

        $this->expectException(PaymentSourceHasSubscriptionsException::class);
        $handler(new DeletePaymentSourceCommand(paymentSourceId: $ulid));
    }
}
