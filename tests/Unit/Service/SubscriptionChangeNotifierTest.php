<?php

// ABOUTME: Unit test for SubscriptionChangeNotifier - the single place subscription changes are announced.
// ABOUTME: Verifies the event is dispatched deferred (DispatchAfterCurrentBusStamp) so it fires post-commit.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Message\Event\SubscriptionsChanged;
use App\Service\SubscriptionChangeNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Uid\Ulid;

final class SubscriptionChangeNotifierTest extends TestCase
{
    public function testAnnouncesSubscriptionsChangedForTheOwnerDeferredUntilTheCurrentBusTransactionCommits(): void
    {
        $ownerUserId = new Ulid();

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::once())->method('dispatch')
            ->with(self::callback(function (object $message) use ($ownerUserId): bool {
                self::assertInstanceOf(Envelope::class, $message);
                $event = $message->getMessage();
                self::assertInstanceOf(SubscriptionsChanged::class, $event);
                self::assertSame($ownerUserId, $event->ownerUserId);
                self::assertNotNull($message->last(DispatchAfterCurrentBusStamp::class));

                return true;
            }))
            ->willReturn(new Envelope(new SubscriptionsChanged($ownerUserId)))
        ;

        new SubscriptionChangeNotifier($eventBus)->notifyChanged($ownerUserId);
    }
}
