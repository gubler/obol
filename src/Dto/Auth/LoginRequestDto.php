<?php

// ABOUTME: Data Transfer Object for the login form - the single email a magic link is requested for.
// ABOUTME: Carries the address from the form to RequestLoginLinkCommand; constraint messages are keyed.

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class LoginRequestDto
{
    #[NotBlank(message: 'auth.login.email.not_blank')]
    #[Email(message: 'auth.login.email.invalid')]
    #[Length(max: 254, maxMessage: 'auth.login.email.too_long')]
    public string $email = '';
}
