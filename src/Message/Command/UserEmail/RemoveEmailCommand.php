<?php

// ABOUTME: Command to remove a secondary address from a user's account.
// ABOUTME: Carries the owner + target row ids; removing the primary is refused by the handler.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class RemoveEmailCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $userEmailId,
    ) {
    }
}
