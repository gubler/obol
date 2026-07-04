<?php

// ABOUTME: Command message for creating a new subscription.
// ABOUTME: Dispatched via command.bus and handled by CreateSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use Symfony\Component\Uid\Ulid;

final readonly class CreateSubscriptionCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public ?Ulid $categoryId,
        public string $name,
        public \DateTimeImmutable $nextRenewal,
        public PaymentPeriod $paymentPeriod,
        public int $paymentPeriodCount,
        public int $cost,
        public Currency $currency,
        public TileColor $color,
        public string $description = '',
        public string $link = '',
        public string $logo = '',
        public ?Ulid $paymentSourceId = null,
    ) {
    }
}
