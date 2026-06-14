<?php

// ABOUTME: Unit tests for UnarchiveSubscriptionHandler verifying subscription reactivation.
// ABOUTME: Tests that handler finds subscription, calls unarchive, and announces the change; throws on not found.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Message\Command\Subscription\UnarchiveSubscriptionCommand;
use App\Message\Command\Subscription\UnarchiveSubscriptionHandler;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Symfony\Component\Uid\Ulid;

test('handler unarchives subscription', function (): void {
    $ulid = new Ulid();

    $subscription = $this->createMock(Subscription::class);
    $subscription->expects($this->once())->method('unarchive');

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($subscription)
    ;

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->once())->method('notifyChanged');

    $handler = new UnarchiveSubscriptionHandler($repository, $notifier);
    $handler(new UnarchiveSubscriptionCommand(subscriptionId: $ulid));
});

test('handler throws when subscription not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
    $notifier->expects($this->never())->method('notifyChanged');

    $handler = new UnarchiveSubscriptionHandler($repository, $notifier);

    $handler(new UnarchiveSubscriptionCommand(subscriptionId: $ulid));
})->throws(InvalidArgumentException::class);
