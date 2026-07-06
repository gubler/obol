<?php

// ABOUTME: Raised when verifying an address another user already holds as a verified row (a lost verify race).
// ABOUTME: The partial unique index on (email) WHERE verified_at IS NOT NULL enforces one owner per verified address.

declare(strict_types=1);

namespace App\Exception;

final class EmailVerifiedElsewhereException extends AbstractSecondaryEmailException
{
    public function __construct()
    {
        parent::__construct('That address is already verified on another account.');
    }

    public function translationKey(): string
    {
        return 'account.email.error.verified_elsewhere';
    }
}
