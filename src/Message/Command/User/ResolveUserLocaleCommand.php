<?php

// ABOUTME: Command persisting a user's locale once it has been inferred from the browser.
// ABOUTME: Carries the owner Ulid + the resolved BCP-47 tag; the handler resolves the Ulid (ADR-0007).

declare(strict_types=1);

namespace App\Message\Command\User;

use Symfony\Component\Uid\Ulid;

final readonly class ResolveUserLocaleCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public string $locale,
    ) {
    }
}
