<?php

// ABOUTME: Marker trigger for the daily payment generation, dispatched on a recurring schedule.
// ABOUTME: Its handler is a thin adapter that dispatches GenerateDuePaymentsCommand to do the work.

declare(strict_types=1);

namespace App\Message\Scheduler;

final readonly class GeneratePaymentsMessage
{
}
