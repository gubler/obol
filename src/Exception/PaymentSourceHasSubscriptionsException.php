<?php

// ABOUTME: Domain exception thrown when attempting to delete a payment source that has subscriptions.
// ABOUTME: Enforces the rule that a payment source in use cannot be deleted; reassign or detach first.

declare(strict_types=1);

namespace App\Exception;

class PaymentSourceHasSubscriptionsException extends \DomainException
{
    public function __construct(string $paymentSourceId)
    {
        parent::__construct(
            \sprintf('Cannot delete payment source "%s" because it has subscriptions assigned.', $paymentSourceId)
        );
    }
}
