<?php

// ABOUTME: Data Transfer Object for subscription creation containing form input data.
// ABOUTME: Used to transfer data from form submission to command handler via CreateSubscriptionCommand.

declare(strict_types=1);

namespace App\Dto\Subscription;

use App\Entity\Category;
use App\Enum\PaymentPeriod;
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
    public ?\DateTimeImmutable $lastPaidDate = null;
    public PaymentPeriod $paymentPeriod = PaymentPeriod::Year;
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
}
