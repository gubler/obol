<?php

// ABOUTME: The languages Obol ships a message catalog for - the choices offered by the settings picker.
// ABOUTME: Each case value is a BCP-47 tag stored on User.locale; label() is a translation key.

declare(strict_types=1);

namespace App\Enum;

/**
 * The curated set of languages the account-settings picker offers. A User.locale is a free BCP-47
 * string (any region formats money/dates natively), but only the tags with a shipped catalog are
 * honest to present as a "language" choice - the rest would show English UI under a foreign label.
 */
enum AppLocale: string
{
    case EnUs = 'en-US';
    case EnGb = 'en-GB';
    case EnCa = 'en-CA';

    public function label(): string
    {
        // Translation key in the `messages` catalog (see ADR-0012), keyed on the case name since the
        // BCP-47 value carries a hyphen that is not key-safe.
        return 'enum.locale.' . match ($this) {
            self::EnUs => 'en_us',
            self::EnGb => 'en_gb',
            self::EnCa => 'en_ca',
        };
    }
}
