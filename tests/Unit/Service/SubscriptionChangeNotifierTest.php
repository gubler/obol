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

final class SubscriptionChangeNotifierTest extends TestCase
{
    public function testAnnouncesSubscriptionsChangedDeferredUntilTheCurrentBusTransactionCommits(): void
    {
        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::once())->method('dispatch')
            ->with(self::callback(function (object $message): bool {
                self::assertInstanceOf(Envelope::class, $message);
                self::assertInstanceOf(SubscriptionsChanged::class, $message->getMessage());
                self::assertNotNull($message->last(DispatchAfterCurrentBusStamp::class));

                return true;
            }))
            ->willReturn(new Envelope(new SubscriptionsChanged()))
        ;

        new SubscriptionChangeNotifier($eventBus)->notifyChanged();
    }
}
