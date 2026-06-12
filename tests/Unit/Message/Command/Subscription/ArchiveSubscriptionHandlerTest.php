<?php

// ABOUTME: Unit tests for ArchiveSubscriptionHandler verifying subscription archival via Doctrine.
// ABOUTME: Tests that handler finds subscription, calls archive, and flushes; throws on not found.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Message\Command\Subscription\ArchiveSubscriptionCommand;
use App\Message\Command\Subscription\ArchiveSubscriptionHandler;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler archives subscription', function (): void {
    $ulid = new Ulid();

    $subscription = $this->createMock(Subscription::class);
    $subscription->expects($this->once())->method('archive');

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($subscription)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->once())->method('notifyChanged');

    $handler = new ArchiveSubscriptionHandler($repository, $entityManager, $notifier);
    $handler(new ArchiveSubscriptionCommand(subscriptionId: $ulid));
});

test('handler throws when subscription not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->never())->method('notifyChanged');

    $handler = new ArchiveSubscriptionHandler($repository, $entityManager, $notifier);

    $handler(new ArchiveSubscriptionCommand(subscriptionId: $ulid));
})->throws(InvalidArgumentException::class);
