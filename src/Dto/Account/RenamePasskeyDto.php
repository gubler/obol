<?php

// ABOUTME: Data Transfer Object for the passkey rename form - the new display name for one credential.
// ABOUTME: Carries the name from the form to RenamePasskeyCommand; constraint messages are keyed.

declare(strict_types=1);

namespace App\Dto\Account;

use App\Entity\PasskeyCredential;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class RenamePasskeyDto
{
    #[NotBlank(message: 'account.passkey.name.not_blank')]
    #[Length(max: PasskeyCredential::NAME_MAX_LENGTH, maxMessage: 'account.passkey.name.too_long')]
    public string $name = '';
}
