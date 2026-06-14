<?php

// ABOUTME: Thin scheduler adapter: on the daily PullExchangeRatesMessage, dispatches the work as a command.
// ABOUTME: Keeps the scheduler off the data path - RefreshExchangeRatesCommand owns the fetch-and-store work.

declare(strict_types=1);

namespace App\Message\Scheduler;

use App\Lib\Bus\CommandBus;
use App\Message\Command\ExchangeRate\RefreshExchangeRatesCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: PullExchangeRatesMessage::class)]
final readonly class PullExchangeRatesHandler
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(PullExchangeRatesMessage $message): void
    {
        $this->commandBus->dispatch(new RefreshExchangeRatesCommand());
    }
}
