<?php

// ABOUTME: Handler that adds a pending secondary address and mails it a verification link.
// ABOUTME: No-ops when the address is already on the account or verified by another user - nothing is inserted or sent.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use App\Entity\UserEmail;
use App\Mail\SecondaryEmailVerificationMailer;
use App\Repository\UserEmailRepository;
use App\Repository\UserRepository;
use Assert\Assertion;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: AddSecondaryEmailCommand::class)]
final readonly class AddSecondaryEmailHandler
{
    public function __construct(
        private UserRepository $users,
        private UserEmailRepository $userEmails,
        private SecondaryEmailVerificationMailer $verificationMailer,
    ) {
    }

    public function __invoke(AddSecondaryEmailCommand $command): void
    {
        $owner = $this->users->getForId($command->ownerUserId);

        $email = trim($command->email);
        Assertion::email($email, 'Secondary email must be a valid email address.');

        // Already on this user's account (primary or any secondary, verified or pending): nothing to do.
        // The per-user unique index would reject a duplicate row anyway; short-circuiting keeps it quiet.
        if ($this->userEmails->findForUserByEmail($owner, $email) instanceof UserEmail) {
            return;
        }

        // Verified by another user: a row added here could never flip to verified (the partial unique
        // index on (email) WHERE verified_at IS NOT NULL forbids it), so do not insert or mail one.
        if ($this->userEmails->findVerifiedByEmail($email) instanceof UserEmail) {
            return;
        }

        $userEmail = new UserEmail(user: $owner, email: $email, isPrimary: false, verifiedAt: null);
        $this->userEmails->persist($userEmail);

        $this->verificationMailer->send($userEmail);
    }
}
