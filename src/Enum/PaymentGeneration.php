<?php

// ABOUTME: Whether Obol generates a subscription's payments automatically or the user manages them.
// ABOUTME: Manual hands renewal and payment management entirely to the user; see ADR-0008.

declare(strict_types=1);

namespace App\Enum;

enum PaymentGeneration: string
{
    case Automated = 'automated';
    case Manual = 'manual';
}
