<?php

declare(strict_types=1);

namespace App\Lib\Bus;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Wrapper for Messenger EventBus.
 */
final readonly class EventBus
{
    public function __construct(
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $eventBus,
    ) {
    }

    /**
     * @param StampInterface[] $stamps
     */
    public function dispatch(object $event, array $stamps = []): void
    {
        $this->eventBus->dispatch(message: $event, stamps: $stamps);
    }
}
