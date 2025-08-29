<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentPeriod: string
{
    case Year = 'year';
    case Month = 'month';
    case Week = 'week';
}
