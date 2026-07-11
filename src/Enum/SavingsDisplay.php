<?php

// ABOUTME: A user's savings-target view preference: how the "you should have saved" figure is computed.
// ABOUTME: MonthOf funds by the due month, MonthBefore a month ahead; Hidden suppresses the figure entirely.

declare(strict_types=1);

namespace App\Enum;

enum SavingsDisplay: string
{
    case MonthOf = 'month_of';
    case MonthBefore = 'month_before';
    case Hidden = 'hidden';

    /**
     * How many months ahead of a renewal its full cost must be saved: 0 funds by the first of the due
     * month itself, 1 by the first of the month before (see ADR-0009). Hidden has no lead of its own -
     * the display layer suppresses the figure rather than asking for one - so it returns 0 as a
     * harmless valid default, the way DateFormat::Iso returns MEDIUM from length().
     */
    public function leadMonths(): int
    {
        return match ($this) {
            self::MonthOf, self::Hidden => 0,
            self::MonthBefore => 1,
        };
    }

    /**
     * Whether the savings target should be shown at all. Hidden suppresses every savings figure - the
     * per-category total on the homepage and the per-subscription figure on the detail page - and the
     * runner skips computing it entirely for a hidden owner.
     */
    public function showsSavings(): bool
    {
        return self::Hidden !== $this;
    }

    public function label(): string
    {
        // Translation key in the `messages` catalog (see ADR-0012); the case value is already key-safe.
        return 'enum.savings_display.' . $this->value;
    }
}
