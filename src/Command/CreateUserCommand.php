<?php

// ABOUTME: Console command (app:user:create) that seeds an account with a primary verified email.
// ABOUTME: Dispatches the CreateUser command via the bus; used for testers, fixtures, and the founder before isolation.

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Lib\Bus\CommandBus;
use App\Message\Command\User\CreateUserCommand as CreateUserMessage;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a passwordless account with the given email as its primary verified address',
)]
final class CreateUserCommand extends Command
{
    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address for the new account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');

        try {
            $user = $this->commandBus->dispatch(command: new CreateUserMessage(email: $email));
        } catch (\Throwable $throwable) {
            if ($this->isDuplicate($throwable)) {
                $io->error(\sprintf('A user with the email "%s" already exists.', $email));

                return Command::FAILURE;
            }

            throw $throwable;
        }

        \assert($user instanceof User);
        $io->success(\sprintf('Created user %s <%s>.', (string) $user->id, $user->email));

        return Command::SUCCESS;
    }

    private function isDuplicate(\Throwable $exception): bool
    {
        for ($cursor = $exception; $cursor instanceof \Throwable; $cursor = $cursor->getPrevious()) {
            if ($cursor instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
    }
}
