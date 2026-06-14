<?php

// ABOUTME: Unit tests for UnarchiveSubscriptionHandler verifying subscription reactivation.
// ABOUTME: Tests that handler finds subscription, calls unarchive, and announces the change; throws on not found.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Subscription;
use App\Message\Command\Subscription\UnarchiveSubscriptionCommand;
use App\Message\Command\Subscription\UnarchiveSubscriptionHandler;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UnarchiveSubscriptionHandlerTest extends TestCase
{
    public function testHandlerUnarchivesSubscription(): void
    {
        $ulid = new Ulid();

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())->method('unarchive');

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($subscription)
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new UnarchiveSubscriptionHandler($repository, $notifier);
        $handler(new UnarchiveSubscriptionCommand(subscriptionId: $ulid));
    }

    public function testHandlerThrowsWhenSubscriptionNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new UnarchiveSubscriptionHandler($repository, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UnarchiveSubscriptionCommand(subscriptionId: $ulid));
    }
}
