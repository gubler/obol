<?php

// ABOUTME: Data Transfer Object for amending a payment, prefilled from the existing payment.
// ABOUTME: Carries the user-asserted amount and paid date to the AmendPaymentCommand.

declare(strict_types=1);

namespace App\Dto\Payment;

use App\Entity\Payment;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

final class AmendPaymentDto
{
    #[NotNull]
    #[GreaterThanOrEqual(value: 1)]
    public ?int $amount = null;

    // A `Y-m-d` string from the date picker; the controller converts it to a CalendarDate. NotBlank (not
    // NotNull) because an empty date field binds to '' rather than null.
    #[NotBlank]
    public ?string $paidDate = null;

    public function __construct(Payment $payment)
    {
        $this->amount = $payment->amount->minorAmount;
        $this->paidDate = (string) $payment->paidDate;
    }
}
