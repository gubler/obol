<?php

// ABOUTME: Query for what is still owed by the end of the current calendar week / month / year.
// ABOUTME: Carries no parameters; the runner reads "now", the display currency, and the week-start seam.

declare(strict_types=1);

namespace App\Message\Query\Report;

use Symfony\Component\Uid\Ulid;

final readonly class FindRemainingInPeriodQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
