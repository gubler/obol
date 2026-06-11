<?php

// ABOUTME: Contract for fetching the latest EUR-pivot exchange rates from an upstream source.
// ABOUTME: Lets the puller depend on an interface (FrankfurterClient is the implementation).

declare(strict_types=1);

namespace App\Service;

interface ExchangeRateProviderInterface
{
    public function fetchLatest(): RateSnapshot;
}
