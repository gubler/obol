<?php

// ABOUTME: Query for one source's drill-down pie: its subscriptions' shares of the source obligation.
// ABOUTME: Carries the source Ulid, or null for the unassigned drill-down; handled by FindPaymentSourceBreakdownRunner.

declare(strict_types=1);

namespace App\Message\Query\Report;

use Symfony\Component\Uid\Ulid;

final readonly class FindPaymentSourceBreakdownQuery
{
    public function __construct(
        public Ulid $ownerUserId,
        // Null selects the unassigned drill-down (subscriptions with no payment source).
        public ?Ulid $paymentSourceId,
    ) {
    }
}
