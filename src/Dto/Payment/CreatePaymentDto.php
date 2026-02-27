<?php

// ABOUTME: Data Transfer Object for payment creation containing form input data.
// ABOUTME: Used to transfer data from form submission to command handler via CreatePaymentCommand.

declare(strict_types=1);

namespace App\Dto\Payment;

use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

final class CreatePaymentDto
{
    #[NotNull]
    #[GreaterThanOrEqual(value: 1)]
    public ?int $amount = null;

    #[NotNull]
    public ?\DateTimeImmutable $paidDate = null;
}
