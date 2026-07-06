<?php

// ABOUTME: Raised when promoting an address that is already the account's primary.
// ABOUTME: A no-op swap; refused so the two-flush path only ever runs on a real change.

declare(strict_types=1);

namespace App\Exception;

final class EmailAlreadyPrimaryException extends AbstractSecondaryEmailException
{
    public function __construct()
    {
        parent::__construct('That address is already the primary.');
    }

    public function translationKey(): string
    {
        return 'account.email.error.already_primary';
    }
}
