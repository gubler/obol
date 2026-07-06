<?php

// ABOUTME: Data Transfer Object for the first-run onboarding form (display name, currency, timezone).
// ABOUTME: Carries form input to CompleteOnboardingCommand; the controller pre-fills the guessed defaults.

declare(strict_types=1);

namespace App\Dto\Onboarding;

use App\Enum\Currency;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Timezone;

final class CompleteOnboardingDto
{
    #[Length(max: 255)]
    public ?string $displayName = null;

    public Currency $displayCurrency = Currency::USD;

    #[Timezone]
    public string $timezone = 'America/New_York';
}
