<?php

// ABOUTME: Handler for ChangePreferencesCommand - applies currency/timezone/locale/date format/savings display.
// ABOUTME: The doctrine_transaction bus middleware flushes the managed entity; no explicit flush here.

declare(strict_types=1);

namespace App\Message\Command\User;

use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: ChangePreferencesCommand::class)]
final readonly class ChangePreferencesHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(ChangePreferencesCommand $command): void
    {
        $user = $this->users->getForId($command->ownerUserId);

        $user->changeDisplayName($command->displayName);
        $user->changePreferences(
            $command->displayCurrency,
            $command->timezone,
            $command->locale,
            $command->dateFormat,
            $command->savingsDisplay,
        );
    }
}
