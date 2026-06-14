<?php

// ABOUTME: Command to generate the due payments across all active subscriptions (the scheduler's daily work).
// ABOUTME: Dispatched by the scheduler adapter and the app:payments:generate console command; handled on command.bus.

declare(strict_types=1);

namespace App\Message\Command\Payment;

final readonly class GenerateDuePaymentsCommand
{
}
