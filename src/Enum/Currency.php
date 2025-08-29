<?php

declare(strict_types=1);

namespace App\Enum;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case JPY = 'JPY';
}
