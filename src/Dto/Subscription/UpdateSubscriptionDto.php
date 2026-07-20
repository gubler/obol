<?php

// ABOUTME: Data Transfer Object for subscription updates containing form input data.
// ABOUTME: Used to transfer data from edit form submission to command handler via UpdateSubscriptionCommand.

declare(strict_types=1);

namespace App\Dto\Subscription;

use App\Entity\Category;
use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

final class UpdateSubscriptionDto
{
    // A category is optional; a subscription may be left uncategorized.
    public ?Category $category;

    // A payment source is optional; a subscription may be left unassigned.
    public ?PaymentSource $paymentSource;

    #[NotBlank]
    public string $name;

    // A `Y-m-d` string from the date picker; the controller converts it to a CalendarDate. A past date
    // is allowed and starts (or keeps) Manual generation. When restarting, the controller checks the
    // date is in the future in the owner's zone and reports a form error otherwise (never a 500).
    public string $nextRenewal;

    /**
     * Only offered for a manual subscription. When checked, the subscription returns to automated
     * generation anchored to `nextRenewal`, which must be a future date (checked in the controller).
     */
    public bool $restartPaymentGeneration = false;

    public PaymentPeriod $paymentPeriod;

    #[GreaterThanOrEqual(value: 1)]
    public int $paymentPeriodCount = 1;

    #[GreaterThanOrEqual(value: 1)]
    public int $cost = 0;

    /**
     * The currency the cost is denominated in. Editable only while the subscription has no payments
     * (the form disables this field once one exists; the entity rejects a change server-side, #129).
     */
    public Currency $currency;

    public string $description = '';

    #[AtLeastOneOf(constraints: [
        new Url(),
        new Blank(),
    ])]
    public string $link = '';

    #[File]
    public ?UploadedFile $logo = null;

    public TileColor $color;

    public function __construct(Subscription $subscription)
    {
        $this->category = $subscription->category;
        $this->paymentSource = $subscription->paymentSource;
        $this->name = $subscription->name;
        $this->nextRenewal = (string) $subscription->nextRenewal;
        $this->paymentPeriod = $subscription->paymentPeriod;
        $this->paymentPeriodCount = $subscription->paymentPeriodCount;
        $this->cost = $subscription->cost->minorAmount;
        $this->currency = $subscription->cost->currency;
        $this->description = $subscription->description;
        $this->link = $subscription->link;
        $this->color = $subscription->color;
    }
}
