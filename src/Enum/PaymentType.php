<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentType: string
{
    case Verified = 'verified';
    case Generated = 'generated';

    public function label(): string
    {
        // Translation key resolved in the `messages` catalog; see ADR-0012.
        return 'enum.payment_type.' . $this->value;
    }
}
