<?php

// ABOUTME: Command message for creating a new subscription.
// ABOUTME: Dispatched via command.bus and handled by CreateSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Enum\PaymentPeriod;

final readonly class CreateSubscriptionCommand
{
    public function __construct(
        public string $categoryId,
        public string $name,
        public \DateTimeImmutable $lastPaidDate,
        public PaymentPeriod $paymentPeriod,
        public int $paymentPeriodCount,
        public int $cost,
        public string $description = '',
        public string $link = '',
        public string $logo = '',
    ) {
    }
}
