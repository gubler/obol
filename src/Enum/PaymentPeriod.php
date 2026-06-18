<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentPeriod: string
{
    case Year = 'year';
    case Month = 'month';
    case Week = 'week';

    /**
     * How many months one billing period spans - the shared basis for normalizing a cost to a
     * monthly equivalent (`Subscription::monthlyCost`) and for scaling a monthly figure back to a
     * per-period one in reports. Weekly cadences use 52 weeks per year.
     */
    public function monthsPerPeriod(): float
    {
        return match ($this) {
            self::Year => 12.0,
            self::Month => 1.0,
            self::Week => 12.0 / 52.0,
        };
    }

    public function label(): string
    {
        // Translation key resolved in the `messages` catalog; see ADR-0012.
        return 'enum.payment_period.' . $this->value;
    }
}
