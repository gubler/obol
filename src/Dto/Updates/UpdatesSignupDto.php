<?php

// ABOUTME: Data Transfer Object for the landing "sign up for updates" form - a single email address.
// ABOUTME: Carries the address from the form to SubscribeToUpdatesCommand; constraint messages are keyed.

declare(strict_types=1);

namespace App\Dto\Updates;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UpdatesSignupDto
{
    #[NotBlank(message: 'landing.updates.email.not_blank')]
    #[Email(message: 'landing.updates.email.invalid')]
    #[Length(max: 254, maxMessage: 'landing.updates.email.too_long')]
    public string $email = '';
}
