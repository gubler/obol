<?php

// ABOUTME: Raised when removing the account's primary address.
// ABOUTME: The primary is the session identity and is always verified, so it must be reassigned before removal.

declare(strict_types=1);

namespace App\Exception;

final class CannotRemovePrimaryEmailException extends AbstractSecondaryEmailException
{
    public function __construct()
    {
        parent::__construct('Cannot remove the primary address; make another address primary first.');
    }

    public function translationKey(): string
    {
        return 'account.email.error.cannot_remove_primary';
    }
}
