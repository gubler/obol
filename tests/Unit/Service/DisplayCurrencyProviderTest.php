<?php

// ABOUTME: Unit test for DisplayCurrencyProvider - resolves its configured ISO code to a Currency.

declare(strict_types=1);

use App\Enum\Currency;
use App\Service\DisplayCurrencyProvider;

test('exposes the currency for its configured code', function (): void {
    expect((new DisplayCurrencyProvider('JPY'))->get())->toBe(Currency::JPY);
});
