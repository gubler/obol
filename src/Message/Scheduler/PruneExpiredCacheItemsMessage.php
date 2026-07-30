<?php

// ABOUTME: Marker trigger for the daily cache prune, dispatched on a recurring schedule.
// ABOUTME: Its handler deletes expired rows from every pruneable pool, cache_items chief among them.

declare(strict_types=1);

namespace App\Message\Scheduler;

final readonly class PruneExpiredCacheItemsMessage
{
}
