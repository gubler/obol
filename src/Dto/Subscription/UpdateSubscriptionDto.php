<?php

// ABOUTME: Data Transfer Object for subscription updates containing form input data.
// ABOUTME: Used to transfer data from edit form submission to command handler via UpdateSubscriptionCommand.

declare(strict_types=1);

namespace App\Dto\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

final class UpdateSubscriptionDto
{
    public Category $category;
    #[NotBlank]
    public string $name;
    public \DateTimeImmutable $lastPaidDate;
    public PaymentPeriod $paymentPeriod;
    #[GreaterThanOrEqual(value: 1)]
    public int $paymentPeriodCount = 1;
    #[GreaterThanOrEqual(value: 0)]
    public int $cost = 0;
    public string $description = '';
    #[AtLeastOneOf(constraints: [
        new Url(),
        new Blank(),
    ])]
    public string $link = '';
    #[File]
    public ?UploadedFile $logo = null;

    public function __construct(Subscription $subscription)
    {
        $this->category = $subscription->category;
        $this->name = $subscription->name;
        $this->lastPaidDate = $subscription->lastPaidDate;
        $this->paymentPeriod = $subscription->paymentPeriod;
        $this->paymentPeriodCount = $subscription->paymentPeriodCount;
        $this->cost = $subscription->cost;
        $this->description = $subscription->description;
        $this->link = $subscription->link;
    }
}
