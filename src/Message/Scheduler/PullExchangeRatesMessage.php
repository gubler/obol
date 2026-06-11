<?php

// ABOUTME: Marker message for the daily exchange-rate pull.
// ABOUTME: Dispatched on a recurring schedule (and by the backfill command) to fetch ECB rates.

declare(strict_types=1);

namespace App\Message\Scheduler;

final readonly class PullExchangeRatesMessage
{
}
