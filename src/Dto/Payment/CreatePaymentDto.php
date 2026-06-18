<?php

// ABOUTME: Data Transfer Object for payment creation containing form input data.
// ABOUTME: Optionally carries a request to resume automated generation with a future renewal date.

declare(strict_types=1);

namespace App\Dto\Payment;

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\When;

final class CreatePaymentDto
{
    #[NotNull]
    #[GreaterThanOrEqual(value: 1)]
    public ?int $amount = null;

    #[NotNull]
    public ?\DateTimeImmutable $paidDate = null;

    /**
     * Only offered for a manual subscription. When checked, `nextRenewal` must be a future date and
     * the subscription returns to automated generation anchored there.
     */
    public bool $restartPaymentGeneration = false;

    #[When(
        expression: 'this.restartPaymentGeneration === true',
        constraints: [
            new NotNull(message: 'payment.validation.restart_renewal_required'),
            new GreaterThan(value: 'today', message: 'payment.validation.renewal_future'),
        ],
    )]
    public ?\DateTimeImmutable $nextRenewal = null;
}
