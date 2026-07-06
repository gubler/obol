<?php

// ABOUTME: Handler for CompleteOnboardingCommand - applies the confirmed first-run settings to the user.
// ABOUTME: The doctrine_transaction bus middleware flushes the managed entity; no explicit flush here.

declare(strict_types=1);

namespace App\Message\Command\Onboarding;

use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CompleteOnboardingCommand::class)]
final readonly class CompleteOnboardingHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(CompleteOnboardingCommand $command): void
    {
        $user = $this->users->getForId($command->ownerUserId);

        $user->completeOnboarding(
            $command->displayName,
            $command->displayCurrency,
            $command->timezone,
        );
    }
}
