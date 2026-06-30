<?php

// ABOUTME: Data Transfer Object for payment source creation containing form input data.
// ABOUTME: Transfers data from form submission to the command handler via CreatePaymentSourceCommand.

declare(strict_types=1);

namespace App\Dto\PaymentSource;

use App\Enum\TileColor;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CreatePaymentSourceDto
{
    #[NotBlank]
    #[Length(max: 255)]
    public string $name = '';

    public string $comment = '';

    public TileColor $color;

    public function __construct()
    {
        // Pre-select a random swatch so a new payment source always starts with a color.
        $this->color = TileColor::random();
    }
}
