<?php

// ABOUTME: Sort modes for the homepage subscription listing, backed by their URL query values.
// ABOUTME: A pure value type - the runner owns the comparators, so the enum stays free of entity coupling.

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionSort: string
{
    case Name = 'name';
    case Renewal = 'renewal';
    case MonthlyCost = 'monthly-cost';
    case Cost = 'cost';

    /**
     * Resolve a `sort` query value to a case, falling back to the default for unknown or missing input.
     */
    public static function fromQuery(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Name;
    }

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Name',
            self::Renewal => 'Renewal',
            self::MonthlyCost => 'Monthly cost',
            self::Cost => 'Cost',
        };
    }
}
