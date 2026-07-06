<?php

// ABOUTME: Command to re-send the verification link for a still-pending secondary address.
// ABOUTME: Carries the owner + target row ids; a verified address is a no-op.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class ResendEmailVerificationCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $userEmailId,
    ) {
    }
}
