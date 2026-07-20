<?php

// ABOUTME: Data Transfer Object for payment creation containing form input data.
// ABOUTME: Optionally carries a request to resume automated generation with a future renewal date.

declare(strict_types=1);

namespace App\Dto\Payment;

use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\When;

final class CreatePaymentDto
{
    #[NotNull]
    #[GreaterThanOrEqual(value: 1)]
    public ?int $amount = null;

    // A `Y-m-d` string from the date picker; the controller converts it to a CalendarDate. NotBlank (not
    // NotNull) because an empty date field binds to '' rather than null.
    #[NotBlank]
    public ?string $paidDate = null;

    /**
     * Only offered for a manual subscription. When checked, the subscription returns to automated
     * generation anchored to `nextRenewal`, which must be a future date (checked in the controller).
     */
    public bool $restartPaymentGeneration = false;

    // The resume anchor (a `Y-m-d` string), required only when restarting; the future-date check lives
    // in the controller so a past date is a form error rather than a 500 from automatePayments().
    #[When(
        expression: 'this.restartPaymentGeneration === true',
        constraints: [
            new NotNull(message: 'payment.validation.restart_renewal_required'),
        ],
    )]
    public ?string $nextRenewal = null;
}
