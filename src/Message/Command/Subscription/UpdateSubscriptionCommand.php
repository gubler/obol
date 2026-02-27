<?php

// ABOUTME: Command message for updating an existing subscription.
// ABOUTME: Dispatched via command.bus and handled by UpdateSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Enum\PaymentPeriod;

final readonly class UpdateSubscriptionCommand
{
    public function __construct(
        public string $subscriptionId,
        public string $categoryId,
        public string $name,
        public \DateTimeImmutable $lastPaidDate,
        public string $description,
        public string $link,
        public string $logo,
        public PaymentPeriod $paymentPeriod,
        public int $paymentPeriodCount,
        public int $cost,
    ) {
    }
}
