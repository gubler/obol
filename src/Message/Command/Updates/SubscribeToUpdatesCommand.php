<?php

// ABOUTME: Command capturing interest from the landing "sign up for updates" form: one email address.
// ABOUTME: Not owner-scoped (the sender is anonymous); the handler is the seam for a future mailing list.

declare(strict_types=1);

namespace App\Message\Command\Updates;

final readonly class SubscribeToUpdatesCommand
{
    public function __construct(
        public string $email,
    ) {
    }
}
