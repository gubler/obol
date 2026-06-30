<?php

// ABOUTME: Command message for creating a new payment source.
// ABOUTME: Dispatched via command.bus and handled by CreatePaymentSourceHandler.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use App\Enum\TileColor;

final readonly class CreatePaymentSourceCommand
{
    public function __construct(
        public string $name,
        public string $comment,
        public TileColor $color,
    ) {
    }
}
