<?php

// ABOUTME: Handler that re-sends the verification link for a pending secondary address.
// ABOUTME: A missing row is a bug; an already-verified row is a silent no-op (nothing to verify).

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use App\Entity\UserEmail;
use App\Mail\SecondaryEmailVerificationMailer;
use App\Repository\UserEmailRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: ResendEmailVerificationCommand::class)]
final readonly class ResendEmailVerificationHandler
{
    public function __construct(
        private UserEmailRepository $userEmails,
        private SecondaryEmailVerificationMailer $verificationMailer,
    ) {
    }

    public function __invoke(ResendEmailVerificationCommand $command): void
    {
        // Owner-scoped: a wrong-owner or unknown id is a bug (the controller already 404'd on null).
        $userEmail = $this->userEmails->findForOwner($command->userEmailId, $command->ownerUserId);
        if (!$userEmail instanceof UserEmail) {
            throw new \InvalidArgumentException(\sprintf('UserEmail with ID "%s" not found.', $command->userEmailId));
        }

        // Already verified: nothing to resend.
        if ($userEmail->isVerified()) {
            return;
        }

        $this->verificationMailer->send($userEmail);
    }
}
