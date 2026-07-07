<?php

// ABOUTME: Handler for ResolveUserLocaleCommand - stores the browser-inferred locale on the user.
// ABOUTME: The doctrine_transaction bus middleware flushes the managed entity; no explicit flush here.

declare(strict_types=1);

namespace App\Message\Command\User;

use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: ResolveUserLocaleCommand::class)]
final readonly class ResolveUserLocaleHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(ResolveUserLocaleCommand $command): void
    {
        $this->users->getForId($command->ownerUserId)->resolveLocale($command->locale);
    }
}
