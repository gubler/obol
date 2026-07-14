<?php

// ABOUTME: Console command (app:user:admin) that grants or revokes the ROLE_ADMIN operator role by email.
// ABOUTME: Bootstraps the first admin from the shell (the UI cannot); requires --grant or --revoke. See ADR-0019.

declare(strict_types=1);

namespace App\Command;

use App\Exception\CannotRemoveLastAdminException;
use App\Exception\UserNotFoundException;
use App\Lib\Bus\CommandBus;
use App\Message\Command\User\SetUserAdminCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:admin',
    description: 'Grant or revoke the ROLE_ADMIN operator role on an account (one of --grant/--revoke is required)',
)]
final class UserAdminCommand extends Command
{
    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address of the account');
        // Grant/revoke is deliberately explicit: there is no default, so a fumbled invocation can never
        // silently make someone an admin. Exactly one of the two is required.
        $this->addOption('grant', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN');
        $this->addOption('revoke', null, InputOption::VALUE_NONE, 'Revoke ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');
        $grant = (bool) $input->getOption('grant');
        $revoke = (bool) $input->getOption('revoke');

        if ($grant === $revoke) {
            $io->error('Specify exactly one of --grant or --revoke.');

            return Command::INVALID;
        }

        try {
            $this->commandBus->dispatch(command: new SetUserAdminCommand(email: $email, admin: $grant));
        } catch (UserNotFoundException|CannotRemoveLastAdminException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success($grant
            ? \sprintf('Granted ROLE_ADMIN to %s.', $email)
            : \sprintf('Revoked ROLE_ADMIN from %s.', $email));

        return Command::SUCCESS;
    }
}
