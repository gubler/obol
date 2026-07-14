<?php

// ABOUTME: Query resolving the account that owns a verified email address, or null when none does.
// ABOUTME: Used by the admin invite flow to reject an email that already belongs to an account.

declare(strict_types=1);

namespace App\Message\Query\User;

final readonly class FindUserByEmailQuery
{
    public function __construct(
        public string $email,
    ) {
    }
}
