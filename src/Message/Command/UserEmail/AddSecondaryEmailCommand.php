<?php

// ABOUTME: Command to add a secondary address to a user's account and mail it a verification link.
// ABOUTME: Runs on the sync command bus so the pending row is visible immediately; delivery is async.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class AddSecondaryEmailCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public string $email,
    ) {
    }
}
