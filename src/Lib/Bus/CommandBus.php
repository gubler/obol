<?php

declare(strict_types=1);

namespace App\Lib\Bus;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Wrapper for Messenger CommandBus.
 */
final readonly class CommandBus
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(object $command): mixed
    {
        /** @var HandledStamp|null $stamp */
        $stamp = $this->commandBus
            ->dispatch(message: $command)
            ->last(stampFqcn: HandledStamp::class)
        ;

        if (null === $stamp) {
            return null;
        }

        return $stamp->getResult();
    }
}
