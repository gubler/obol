<?php

// ABOUTME: Handler for SubscribeToUpdatesCommand - records a landing updates-signup interest.
// ABOUTME: For now it only logs; this is the seam where a real mailing-list integration will land.

declare(strict_types=1);

namespace App\Message\Command\Updates;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: SubscribeToUpdatesCommand::class)]
final readonly class SubscribeToUpdatesHandler
{
    public function __construct(
        private LoggerInterface $appLogger,
    ) {
    }

    public function __invoke(SubscribeToUpdatesCommand $command): void
    {
        // No mailing list yet: capture the interest in the log so it is not lost during closed testing.
        // When a mailing-list provider is wired in, the outbound call replaces this line.
        $this->appLogger->info('Landing updates signup.', ['email' => $command->email]);
    }
}
