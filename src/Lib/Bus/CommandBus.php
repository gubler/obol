<?php

declare(strict_types=1);

namespace App\Lib\Bus;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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
        try {
            /** @var HandledStamp|null $stamp */
            $stamp = $this->commandBus
                ->dispatch(message: $command)
                ->last(stampFqcn: HandledStamp::class)
            ;
        } catch (HandlerFailedException $handlerFailedException) {
            // A command has exactly one handler, so surface that handler's real exception rather than the
            // messenger envelope. Callers (controllers) can then catch domain errors by type instead of
            // unwrapping the wrapper at every site.
            throw $handlerFailedException->getPrevious() ?? $handlerFailedException;
        }

        if (null === $stamp) {
            return null;
        }

        return $stamp->getResult();
    }
}
