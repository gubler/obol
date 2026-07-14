<?php

// ABOUTME: Command to grant or revoke ROLE_ADMIN on the account with the given email (admin=false revokes).

declare(strict_types=1);

namespace App\Message\Command\User;

final readonly class SetUserAdminCommand
{
    public function __construct(
        public string $email,
        public bool $admin,
    ) {
    }
}
