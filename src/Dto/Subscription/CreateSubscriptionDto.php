<?php

// ABOUTME: Data Transfer Object for subscription creation containing form input data.
// ABOUTME: Used to transfer data from form submission to command handler via CreateSubscriptionCommand.

declare(strict_types=1);

namespace App\Dto\Subscription;

use App\Entity\Category;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Url;

final class CreateSubscriptionDto
{
    #[NotNull]
    public ?Category $category = null;

    #[NotBlank]
    public string $name = '';

    #[NotNull]
    public ?\DateTimeImmutable $nextRenewal = null;

    public PaymentPeriod $paymentPeriod = PaymentPeriod::Year;

    #[GreaterThanOrEqual(value: 1)]
    public int $paymentPeriodCount = 1;

    #[GreaterThanOrEqual(value: 1)]
    public int $cost = 0;

    /**
     * The currency the cost is denominated in. Defaults to the display currency (USD for now,
     * #131); freely chosen at creation and fixed once the first payment is recorded (#129).
     */
    public Currency $currency = Currency::USD;

    public string $description = '';

    #[AtLeastOneOf(constraints: [
        new Url(),
        new Blank(),
    ])]
    public string $link = '';

    #[File]
    public ?UploadedFile $logo = null;

    public TileColor $color;

    public function __construct()
    {
        // Pre-select a random swatch so a new subscription always starts with a color.
        $this->color = TileColor::random();
    }
}
