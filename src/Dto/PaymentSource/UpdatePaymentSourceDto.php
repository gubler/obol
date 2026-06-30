<?php

// ABOUTME: Data Transfer Object for payment source updates containing form input data.
// ABOUTME: Transfers data from the edit form to the command handler via UpdatePaymentSourceCommand.

declare(strict_types=1);

namespace App\Dto\PaymentSource;

use App\Entity\PaymentSource;
use App\Enum\TileColor;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UpdatePaymentSourceDto
{
    #[NotBlank]
    #[Length(max: 255)]
    public string $name;

    public string $comment;

    public TileColor $color;

    public function __construct(PaymentSource $paymentSource)
    {
        $this->name = $paymentSource->name;
        $this->comment = $paymentSource->comment;
        $this->color = $paymentSource->color;
    }
}
