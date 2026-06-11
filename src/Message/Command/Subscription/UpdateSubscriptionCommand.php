<?php

// ABOUTME: Command message for updating an existing subscription.
// ABOUTME: Dispatched via command.bus and handled by UpdateSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use Symfony\Component\Uid\Ulid;

final readonly class UpdateSubscriptionCommand
{
    public function __construct(
        public Ulid $subscriptionId,
        public Ulid $categoryId,
        public string $name,
        public \DateTimeImmutable $nextRenewal,
        public string $description,
        public string $link,
        public string $logo,
        public PaymentPeriod $paymentPeriod,
        public int $paymentPeriodCount,
        public int $cost,
        public Currency $currency,
        public TileColor $color,
        public bool $restartPaymentGeneration = false,
    ) {
    }
}
