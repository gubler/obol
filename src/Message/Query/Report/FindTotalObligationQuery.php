<?php

// ABOUTME: Query for the total obligation across active subscriptions, for the homepage Global Totals capstone.
// ABOUTME: Carries the owner; the runner reads that user's display currency to present converted totals.

declare(strict_types=1);

namespace App\Message\Query\Report;

use Symfony\Component\Uid\Ulid;

final readonly class FindTotalObligationQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
