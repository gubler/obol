<?php

// ABOUTME: Command to fetch the day's EUR-pivot rates and append any not yet stored (the scheduler's daily work).
// ABOUTME: Dispatched by the scheduler adapter and the app:exchange-rates:pull console command; handled on command.bus.

declare(strict_types=1);

namespace App\Message\Command\ExchangeRate;

final readonly class RefreshExchangeRatesCommand
{
}
