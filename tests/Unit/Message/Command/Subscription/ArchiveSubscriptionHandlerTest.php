<?php

// ABOUTME: Unit tests for ArchiveSubscriptionHandler verifying subscription archival.
// ABOUTME: Tests that handler finds subscription, calls archive, and announces the change; throws on not found.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Subscription;
use App\Message\Command\Subscription\ArchiveSubscriptionCommand;
use App\Message\Command\Subscription\ArchiveSubscriptionHandler;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ArchiveSubscriptionHandlerTest extends TestCase
{
    public function testHandlerArchivesSubscription(): void
    {
        $ulid = new Ulid();

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())->method('archive');

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn($subscription)
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new ArchiveSubscriptionHandler($repository, $notifier);
        $handler(new ArchiveSubscriptionCommand(ownerUserId: new Ulid(), subscriptionId: $ulid));
    }

    public function testHandlerThrowsWhenSubscriptionNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn(null)
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new ArchiveSubscriptionHandler($repository, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new ArchiveSubscriptionCommand(ownerUserId: new Ulid(), subscriptionId: $ulid));
    }
}
