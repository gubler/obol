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
        // Translation keys resolved in the `messages` catalog; see ADR-0012.
        return match ($this) {
            self::Name => 'enum.subscription_sort.name',
            self::Renewal => 'enum.subscription_sort.renewal',
            self::MonthlyCost => 'enum.subscription_sort.monthly_cost',
            self::Cost => 'enum.subscription_sort.cost',
        };
    }
}
