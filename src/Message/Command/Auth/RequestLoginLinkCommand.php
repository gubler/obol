<?php

// ABOUTME: Command asking for a magic-link email to be sent to an address, if it belongs to an account.
// ABOUTME: Routed to the async transport so the account lookup runs off-request (timing-flat for all emails).

declare(strict_types=1);

namespace App\Message\Command\Auth;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class RequestLoginLinkCommand
{
    public function __construct(
        public string $email,
    ) {
    }
}
