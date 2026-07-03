<?php

// ABOUTME: Command to create a new account for an email address, with that address as its primary verified email.

declare(strict_types=1);

namespace App\Message\Command\User;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
    ) {
    }
}
