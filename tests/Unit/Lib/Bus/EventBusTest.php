<?php

// ABOUTME: Unit tests for EventBus wrapper verifying message dispatch delegation.
// ABOUTME: Tests that EventBus forwards dispatch calls to the underlying Messenger bus.

declare(strict_types=1);

use App\Lib\Bus\EventBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

test('dispatches event to underlying bus', function (): void {
    $event = new \stdClass();

    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects($this->once())
        ->method('dispatch')
        ->with($event, [])
        ->willReturn(new Envelope($event))
    ;

    $eventBus = new EventBus($messageBus);
    $eventBus->dispatch($event);
});

test('passes stamps to underlying bus', function (): void {
    $event = new \stdClass();
    $stamp = $this->createMock(\Symfony\Component\Messenger\Stamp\StampInterface::class);

    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects($this->once())
        ->method('dispatch')
        ->with($event, [$stamp])
        ->willReturn(new Envelope($event))
    ;

    $eventBus = new EventBus($messageBus);
    $eventBus->dispatch($event, [$stamp]);
});
