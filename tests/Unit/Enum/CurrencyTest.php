<?php

// ABOUTME: Unit tests for the Currency enum - the curated ECB/Frankfurter-quoted set.
// ABOUTME: Verifies ISO-code backing, the supported set, fraction digits, symbol, and label.

declare(strict_types=1);

use App\Enum\Currency;

test('is a string-backed enum of ISO 4217 codes', function (): void {
    expect(Currency::USD->value)->toBe('USD')
        ->and(Currency::from('JPY'))->toBe(Currency::JPY)
    ;
});

test('covers the Frankfurter/ECB-quoted currency set and nothing more', function (): void {
    expect(Currency::cases())->toHaveCount(30)
        ->and(Currency::tryFrom('USD'))->not->toBeNull()
        ->and(Currency::tryFrom('EUR'))->not->toBeNull()
        ->and(Currency::tryFrom('JPY'))->not->toBeNull()
        // Not all of ISO 4217: a currency we cannot get a rate for is intentionally absent.
        ->and(Currency::tryFrom('XAU'))->toBeNull()
        ->and(Currency::tryFrom('KWD'))->toBeNull()
    ;
});

test('reports fraction digits per currency', function (): void {
    expect(Currency::USD->fractionDigits())->toBe(2)
        ->and(Currency::EUR->fractionDigits())->toBe(2)
        ->and(Currency::JPY->fractionDigits())->toBe(0)
    ;
});

test('exposes a symbol and a human label', function (): void {
    expect(Currency::USD->symbol())->toBe('$')
        ->and(Currency::JPY->symbol())->toBe('¥')
        ->and(Currency::GBP->symbol())->toBe('£')
        ->and(Currency::USD->label())->toContain('Dollar')
    ;
});
