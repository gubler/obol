<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentType: string
{
    case Verified = 'verified';
    case Generated = 'generated';
}
