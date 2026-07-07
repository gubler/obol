<?php

// ABOUTME: Command to change a user's formatting/locale preferences from the account settings hub.
// ABOUTME: Carries the owner Ulid + the chosen settings; the handler resolves the Ulid (ADR-0007).

declare(strict_types=1);

namespace App\Message\Command\User;

use App\Enum\Currency;
use App\Enum\DateFormat;
use Symfony\Component\Uid\Ulid;

final readonly class ChangePreferencesCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public ?string $displayName,
        public Currency $displayCurrency,
        public string $timezone,
        public string $locale,
        public DateFormat $dateFormat,
    ) {
    }
}
