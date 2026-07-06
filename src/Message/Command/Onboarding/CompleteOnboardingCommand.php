<?php

// ABOUTME: Command carrying a user's confirmed first-run settings (name, display currency, timezone).
// ABOUTME: Carries the owner Ulid, never the entity; the handler resolves it (see ADR-0007).

declare(strict_types=1);

namespace App\Message\Command\Onboarding;

use App\Enum\Currency;
use Symfony\Component\Uid\Ulid;

final readonly class CompleteOnboardingCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public ?string $displayName,
        public Currency $displayCurrency,
        public string $timezone,
    ) {
    }
}
