<?php

// ABOUTME: Command message for recording a payment on a subscription.
// ABOUTME: Optionally resumes automated generation (with a future renewal) for a manual subscription.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use Symfony\Component\Uid\Ulid;

final readonly class CreatePaymentCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $subscriptionId,
        public int $amount,
        public \DateTimeImmutable $paidDate,
        public bool $restartPaymentGeneration = false,
        public ?\DateTimeImmutable $nextRenewal = null,
    ) {
    }
}
