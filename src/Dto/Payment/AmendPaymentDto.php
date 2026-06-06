<?php

// ABOUTME: Data Transfer Object for amending a payment, prefilled from the existing payment.
// ABOUTME: Carries the user-asserted amount and paid date to the AmendPaymentCommand.

declare(strict_types=1);

namespace App\Dto\Payment;

use App\Entity\Payment;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

final class AmendPaymentDto
{
    #[NotNull]
    #[GreaterThanOrEqual(value: 1)]
    public ?int $amount = null;

    #[NotNull]
    public ?\DateTimeImmutable $paidDate = null;

    public function __construct(Payment $payment)
    {
        $this->amount = $payment->amount;
        $this->paidDate = $payment->paidDate;
    }
}
