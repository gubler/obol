<?php

// ABOUTME: Command to mark a pending secondary address verified, identified by its row id.
// ABOUTME: Dispatched by the public verify controller after the signed link checks out.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class VerifyEmailCommand
{
    public function __construct(
        public Ulid $userEmailId,
    ) {
    }
}
