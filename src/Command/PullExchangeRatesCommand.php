<?php

// ABOUTME: Console command to pull the latest exchange rates now (initial backfill / manual refresh).
// ABOUTME: Dispatches the same PullExchangeRatesMessage the daily scheduler uses.

declare(strict_types=1);

namespace App\Command;

use App\Lib\Bus\CommandBus;
use App\Message\Scheduler\PullExchangeRatesMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:exchange-rates:pull',
    description: 'Fetch the latest EUR-pivot exchange rates and store any not yet recorded for today',
)]
final class PullExchangeRatesCommand extends Command
{
    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(command: new PullExchangeRatesMessage());

        new SymfonyStyle($input, $output)->success('Exchange rates pulled.');

        return Command::SUCCESS;
    }
}
