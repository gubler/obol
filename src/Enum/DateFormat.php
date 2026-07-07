<?php

// ABOUTME: A user's date-rendering preference, independent of their locale (some want a fixed order).
// ABOUTME: Cases are named for the pattern + separator; the case value IS the ICU pattern it renders.

declare(strict_types=1);

namespace App\Enum;

enum DateFormat: string
{
    case LocaleDefault = 'locale_default';
    case YearMonthDayDash = 'yyyy-MM-dd';
    case MonthDayYearSlash = 'MM/dd/yyyy';
    case DayMonthYearSlash = 'dd/MM/yyyy';

    /**
     * The ICU pattern for this format - the case value itself - or null for LocaleDefault, which defers
     * to the locale's medium date length. A pattern fixes the digit order regardless of locale. Cases
     * are named for the pattern (not a region) so a new order is named for what it is, not where it is used.
     */
    public function pattern(): ?string
    {
        return self::LocaleDefault === $this ? null : $this->value;
    }

    public function label(): string
    {
        // Translation key resolved in the `messages` catalog (see ADR-0012). Keyed on the case name
        // rather than the value, since the value carries slashes/dashes that are not key-safe.
        return 'enum.date_format.' . match ($this) {
            self::LocaleDefault => 'locale_default',
            self::YearMonthDayDash => 'year_month_day_dash',
            self::MonthDayYearSlash => 'month_day_year_slash',
            self::DayMonthYearSlash => 'day_month_year_slash',
        };
    }
}
