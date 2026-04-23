<?php

// ABOUTME: Unit tests for DeleteSubscriptionHandler verifying subscription removal via Doctrine.
// ABOUTME: Tests that handler finds subscription, removes it, and flushes; throws on not found.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Message\Command\Subscription\DeleteSubscriptionCommand;
use App\Message\Command\Subscription\DeleteSubscriptionHandler;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler removes subscription', function (): void {
    $ulid = new Ulid();

    $subscription = $this->createMock(Subscription::class);

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($subscription)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())
        ->method('remove')
        ->with($subscription)
    ;
    $entityManager->expects($this->once())->method('flush');

    $handler = new DeleteSubscriptionHandler($repository, $entityManager);
    $handler(new DeleteSubscriptionCommand(subscriptionId: $ulid->toRfc4122()));
});

test('handler throws when subscription not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new DeleteSubscriptionHandler($repository, $entityManager);

    $handler(new DeleteSubscriptionCommand(subscriptionId: $ulid->toRfc4122()));
})->throws(InvalidArgumentException::class);
