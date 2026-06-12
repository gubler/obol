<?php

// ABOUTME: Unit test for SubscriptionChangeNotifier - the single place subscription changes are announced.
// ABOUTME: Verifies the event is dispatched deferred (DispatchAfterCurrentBusStamp) so it fires post-commit.

declare(strict_types=1);

use App\Message\Event\SubscriptionsChanged;
use App\Service\SubscriptionChangeNotifier;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

test('announces SubscriptionsChanged deferred until the current bus transaction commits', function (): void {
    $eventBus = $this->createMock(MessageBusInterface::class);
    $eventBus->expects($this->once())->method('dispatch')
        ->with($this->callback(function (object $message): bool {
            expect($message)->toBeInstanceOf(Envelope::class);
            /* @var Envelope $message */
            expect($message->getMessage())->toBeInstanceOf(SubscriptionsChanged::class)
                ->and($message->last(DispatchAfterCurrentBusStamp::class))->not->toBeNull()
            ;

            return true;
        }))
        ->willReturn(new Envelope(new SubscriptionsChanged()))
    ;

    new SubscriptionChangeNotifier($eventBus)->notifyChanged();
});
