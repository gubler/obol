<?php

// ABOUTME: Domain exception thrown when revoking ROLE_ADMIN would leave the system with no admin.
// ABOUTME: Guards the invariant that at least one admin always remains, so the operator surface is never locked out.

declare(strict_types=1);

namespace App\Exception;

class CannotRemoveLastAdminException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(\sprintf('Cannot revoke admin from "%s": at least one admin must remain.', $email));
    }
}
