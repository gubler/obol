<?php

// ABOUTME: Data Transfer Object for the preferences form in the account hub (currency/timezone/language/dates).
// ABOUTME: The controller pre-fills the user's current settings; carries them to ChangePreferencesCommand.

declare(strict_types=1);

namespace App\Dto\Account;

use App\Enum\AppLocale;
use App\Enum\Currency;
use App\Enum\DateFormat;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Timezone;

final class ChangePreferencesDto
{
    #[Length(max: 255)]
    public ?string $displayName = null;

    public Currency $displayCurrency = Currency::USD;

    #[Timezone]
    public string $timezone = 'America/New_York';

    // Null when the current locale has no shipped catalog (e.g. a browser-guessed region); the picker
    // then shows a placeholder and requires a choice on save.
    #[NotNull]
    public ?AppLocale $language = null;

    public DateFormat $dateFormat = DateFormat::Medium;
}
