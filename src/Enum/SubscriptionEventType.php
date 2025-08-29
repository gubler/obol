<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionEventType: string
{
    case CostChange = 'costChange';
    case Update = 'update';
    case Archive = 'archive';
    case Unarchive = 'unarchive';
}
