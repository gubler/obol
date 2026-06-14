<?php

// ABOUTME: Marker trigger for the daily exchange-rate pull, dispatched on a recurring schedule.
// ABOUTME: Its handler is a thin adapter that dispatches RefreshExchangeRatesCommand to do the work.

declare(strict_types=1);

namespace App\Message\Scheduler;

final readonly class PullExchangeRatesMessage
{
}
