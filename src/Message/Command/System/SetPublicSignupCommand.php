<?php

// ABOUTME: Command to turn public self-registration on or off in the system settings singleton.

declare(strict_types=1);

namespace App\Message\Command\System;

final readonly class SetPublicSignupCommand
{
    public function __construct(
        public bool $enabled,
    ) {
    }
}
