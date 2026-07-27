<?php

// ABOUTME: Presentation-only bundle for the product tour: a staged HomepageListing plus its totals capstone.
// ABOUTME: Built entirely in memory from a non-persisted sample subscription; never touches the database.

declare(strict_types=1);

namespace App\Tour;

use App\Message\Query\Report\TotalObligation;
use App\Message\Query\Subscription\HomepageListing;

final readonly class SampleDashboard
{
    public function __construct(
        public HomepageListing $listing,
        public TotalObligation $capstone,
    ) {
    }
}
