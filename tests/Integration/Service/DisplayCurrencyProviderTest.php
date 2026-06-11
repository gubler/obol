<?php

// ABOUTME: Integration test that DisplayCurrencyProvider is injectable and reads app.display_currency.

declare(strict_types=1);

use App\Enum\Currency;
use App\Service\DisplayCurrencyProvider;

test('is wired from the app.display_currency parameter, defaulting to USD', function (): void {
    $this->createClient();
    $provider = $this->getContainer()->get(DisplayCurrencyProvider::class);

    expect($provider)->toBeInstanceOf(DisplayCurrencyProvider::class)
        ->and($provider->get())->toBe(Currency::USD)
    ;
});
