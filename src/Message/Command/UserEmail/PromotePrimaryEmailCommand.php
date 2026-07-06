<?php

// ABOUTME: Command to make a verified secondary address the account's primary (session identity).
// ABOUTME: Carries the owner + target row ids; the handler runs the two-flush swap.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class PromotePrimaryEmailCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $userEmailId,
    ) {
    }
}
