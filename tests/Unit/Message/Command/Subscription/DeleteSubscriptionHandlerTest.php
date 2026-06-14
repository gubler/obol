<?php

// ABOUTME: Unit tests for DeleteSubscriptionHandler verifying subscription removal via Doctrine.
// ABOUTME: Tests that handler finds subscription and removes it; throws on not found.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Subscription;
use App\Message\Command\Subscription\DeleteSubscriptionCommand;
use App\Message\Command\Subscription\DeleteSubscriptionHandler;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class DeleteSubscriptionHandlerTest extends TestCase
{
    public function testHandlerRemovesSubscription(): void
    {
        $ulid = new Ulid();

        $subscription = self::createStub(Subscription::class);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($subscription)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('remove')
            ->with($subscription)
        ;
        // The command bus owns the transaction (doctrine_transaction middleware); the handler never flushes.
        $entityManager->expects(self::never())->method('flush');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new DeleteSubscriptionHandler($repository, $entityManager, $notifier);
        $handler(new DeleteSubscriptionCommand(subscriptionId: $ulid));
    }

    public function testHandlerThrowsWhenSubscriptionNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $entityManager = self::createStub(EntityManagerInterface::class);

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new DeleteSubscriptionHandler($repository, $entityManager, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new DeleteSubscriptionCommand(subscriptionId: $ulid));
    }
}
