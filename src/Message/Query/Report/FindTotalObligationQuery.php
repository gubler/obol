<?php

// ABOUTME: Query for the total obligation across active subscriptions, for the homepage Global Totals capstone.
// ABOUTME: Carries no parameters; the runner reads the display currency from DisplayCurrencyProvider.

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
