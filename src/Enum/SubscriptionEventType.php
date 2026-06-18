<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionEventType: string
{
    case CostChange = 'costChange';
    case Update = 'update';
    case Archive = 'archive';
    case Unarchive = 'unarchive';

    public function label(): string
    {
        // Translation key resolved in the `messages` catalog; see ADR-0012. The `costChange` backing
        // value is camelCase, so the keys are spelled out as snake_case rather than derived.
        return match ($this) {
            self::CostChange => 'enum.subscription_event_type.cost_change',
            self::Update => 'enum.subscription_event_type.update',
            self::Archive => 'enum.subscription_event_type.archive',
            self::Unarchive => 'enum.subscription_event_type.unarchive',
        };
    }
}
