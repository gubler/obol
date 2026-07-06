<?php

// ABOUTME: Handler that flips a pending address to verified, or reports the lost-race conflict.
// ABOUTME: Idempotent for refresh-clicks; flushes early to map the partial-index violation to a domain error.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use App\Entity\UserEmail;
use App\Exception\EmailVerifiedElsewhereException;
use App\Repository\UserEmailRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: VerifyEmailCommand::class)]
final readonly class VerifyEmailHandler
{
    public function __construct(
        private UserEmailRepository $userEmails,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifyEmailCommand $command): void
    {
        $userEmail = $this->userEmails->find($command->userEmailId);
        if (!$userEmail instanceof UserEmail) {
            throw new \InvalidArgumentException(\sprintf('UserEmail with ID "%s" not found.', $command->userEmailId));
        }

        // Idempotent: re-clicking an already-verified link is a no-op, not an error.
        if ($userEmail->isVerified()) {
            return;
        }

        $userEmail->markVerified($this->clock->now());

        try {
            // Flush here (not at the middleware's end-of-handler flush) so the partial unique index on
            // (email) WHERE verified_at IS NOT NULL surfaces now and maps to a friendly domain error.
            $this->userEmails->flush();
        } catch (UniqueConstraintViolationException) {
            throw new EmailVerifiedElsewhereException();
        }
    }
}
