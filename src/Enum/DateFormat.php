<?php

// ABOUTME: A user's date-rendering style: three locale-aware ICU lengths plus a fixed ISO pattern.
// ABOUTME: Long/Medium/Short follow the ambient locale's own format; Iso pins yyyy-MM-dd everywhere.

declare(strict_types=1);

namespace App\Enum;

enum DateFormat: string
{
    case Long = 'long';
    case Medium = 'medium';
    case Short = 'short';
    case Iso = 'iso';

    /**
     * The IntlDateFormatter length for the locale-aware styles - the app follows the locale's own Long,
     * Medium, or Short form rather than encoding a per-locale pattern. Iso has no length of its own (it
     * renders through pattern()), so it returns MEDIUM as a harmless valid default.
     */
    public function length(): int
    {
        return match ($this) {
            self::Long => \IntlDateFormatter::LONG,
            self::Medium, self::Iso => \IntlDateFormatter::MEDIUM,
            self::Short => \IntlDateFormatter::SHORT,
        };
    }

    /**
     * The fixed ICU pattern for Iso (locale-independent yyyy-MM-dd), or null for the locale-aware
     * styles, which defer to their length() in the ambient locale.
     */
    public function pattern(): ?string
    {
        return self::Iso === $this ? 'yyyy-MM-dd' : null;
    }

    /**
     * The fixed ICU pattern for rendering a date *and time* in the Iso style (a 24-hour clock), or null
     * for the locale-aware styles, which pair their date length with the locale's own short time.
     */
    public function dateTimePattern(): ?string
    {
        return self::Iso === $this ? 'yyyy-MM-dd HH:mm' : null;
    }

    public function label(): string
    {
        // Translation key in the `messages` catalog (see ADR-0012); the case value is already key-safe.
        return 'enum.date_format.' . $this->value;
    }
}
