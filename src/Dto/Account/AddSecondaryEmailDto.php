<?php

// ABOUTME: Data Transfer Object for the add-secondary-email form - the address the user wants to add.
// ABOUTME: Carries the address to AddSecondaryEmailCommand; constraint messages are keyed.

declare(strict_types=1);

namespace App\Dto\Account;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AddSecondaryEmailDto
{
    #[NotBlank(message: 'account.email.add.invalid')]
    #[Email(message: 'account.email.add.invalid')]
    public string $email = '';
}
