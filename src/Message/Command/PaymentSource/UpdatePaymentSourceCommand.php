<?php

// ABOUTME: Command message for updating an existing payment source.
// ABOUTME: Dispatched via command.bus and handled by UpdatePaymentSourceHandler.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use App\Enum\TileColor;
use Symfony\Component\Uid\Ulid;

final readonly class UpdatePaymentSourceCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $paymentSourceId,
        public string $name,
        public string $comment,
        public TileColor $color,
    ) {
    }
}
