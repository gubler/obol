<?php

// ABOUTME: Console command to generate due payments now (manual catch-up run).
// ABOUTME: Dispatches GenerateDuePaymentsCommand - the same work the daily scheduler triggers.

declare(strict_types=1);

namespace App\Command;

use App\Lib\Bus\CommandBus;
use App\Message\Command\Payment\GenerateDuePaymentsCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:payments:generate',
    description: 'Generate the due payments for every active subscription whose renewal has passed',
)]
final class GeneratePaymentsCommand extends Command
{
    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(command: new GenerateDuePaymentsCommand());

        new SymfonyStyle($input, $output)->success('Due payments generated.');

        return Command::SUCCESS;
    }
}
