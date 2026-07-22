<?php

// ABOUTME: Form DTO for the "move all subscriptions" action - the destination payment source.
// ABOUTME: The form's owner-scoped choice list is what constrains target to one of the user's own sources.

declare(strict_types=1);

namespace App\Dto\PaymentSource;

use App\Entity\PaymentSource;
use Symfony\Component\Validator\Constraints as Assert;

final class ReassignSubscriptionsDto
{
    #[Assert\NotNull]
    public ?PaymentSource $target = null;
}
