<?php

// ABOUTME: Data Transfer Object for subscription creation containing form input data.
// ABOUTME: Used to transfer data from form submission to command handler via CreateSubscriptionCommand.

declare(strict_types=1);

namespace App\Dto\Subscription;

use App\Entity\Category;
use App\Entity\PaymentSource;
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
    // A category is optional; a subscription may be left uncategorized.
    public ?Category $category = null;

    // A payment source is optional; a subscription may be left unassigned.
    public ?PaymentSource $paymentSource = null;

    #[NotBlank]
    public string $name = '';

    // A `Y-m-d` string from the date picker (the form binds `input => string`); the controller converts
    // it to a CalendarDate. Any date is allowed - a past renewal simply starts generation on Manual.
    // NotBlank (not NotNull) because an empty date field binds to '' rather than null.
    #[NotBlank]
    public ?string $nextRenewal = null;

    public PaymentPeriod $paymentPeriod = PaymentPeriod::Month;

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
