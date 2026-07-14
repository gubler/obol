<?php

// ABOUTME: Domain exception thrown when an operation targets an account by email that does not exist.
// ABOUTME: The admin promote flow raises it so the console can report the bad email and exit non-zero.

declare(strict_types=1);

namespace App\Exception;

class UserNotFoundException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(\sprintf('No user found with the email "%s".', $email));
    }
}
