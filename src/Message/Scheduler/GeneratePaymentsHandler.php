<?php

// ABOUTME: Thin scheduler adapter: on the daily GeneratePaymentsMessage, dispatches the work as a command.
// ABOUTME: Keeps the scheduler off the data path - GenerateDuePaymentsCommand owns the find-and-record work.

declare(strict_types=1);

namespace App\Message\Scheduler;

use App\Lib\Bus\CommandBus;
use App\Message\Command\Payment\GenerateDuePaymentsCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: GeneratePaymentsMessage::class)]
final readonly class GeneratePaymentsHandler
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(GeneratePaymentsMessage $message): void
    {
        $this->commandBus->dispatch(new GenerateDuePaymentsCommand());
    }
}
