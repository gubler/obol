<?php

// ABOUTME: Raised when promoting an address to primary that has not been verified yet.
// ABOUTME: A primary address must always be verified (UserEmail invariant), so this is refused up front.

declare(strict_types=1);

namespace App\Exception;

final class CannotPromoteUnverifiedEmailException extends AbstractSecondaryEmailException
{
    public function __construct()
    {
        parent::__construct('Cannot make an unverified address primary.');
    }

    public function translationKey(): string
    {
        return 'account.email.error.cannot_promote_unverified';
    }
}
