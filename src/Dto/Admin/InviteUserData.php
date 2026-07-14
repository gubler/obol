<?php

// ABOUTME: Data Transfer Object for the admin invite-user form - the email address to invite.
// ABOUTME: The controller carries it to CreateUserCommand + RequestLoginLinkCommand once validated.

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

final class InviteUserData
{
    #[NotBlank]
    #[Email]
    public ?string $email = null;
}
