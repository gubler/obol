<?php

// ABOUTME: Unit tests for the TileColor palette enum and its Tailwind gradient mapping.
// ABOUTME: Verifies the 18 swatches, well-formed gradient classes, labels, and random selection.

declare(strict_types=1);

use App\Enum\TileColor;

test('defines the full palette of eighteen swatches', function (): void {
    expect(TileColor::cases())->toHaveCount(18);
});

test('is a string-backed enum', function (): void {
    expect(TileColor::Blue->value)->toBe('blue')
        ->and(TileColor::from('charcoal'))->toBe(TileColor::Charcoal)
    ;
});

test('every swatch yields a well-formed top-to-bottom gradient', function (): void {
    foreach (TileColor::cases() as $color) {
        expect($color->gradientClasses())
            ->toStartWith('bg-linear-to-b ')
            ->toContain('from-')
            ->toContain('to-')
        ;
    }
});

test('maps swatches to their chosen Tailwind shades', function (): void {
    expect(TileColor::Red->gradientClasses())->toBe('bg-linear-to-b from-red-600 to-red-950')
        ->and(TileColor::Magenta->gradientClasses())->toBe('bg-linear-to-b from-fuchsia-500 to-fuchsia-950')
        ->and(TileColor::Cyan->gradientClasses())->toBe('bg-linear-to-b from-sky-500 to-sky-950')
        ->and(TileColor::Charcoal->gradientClasses())->toBe('bg-linear-to-b from-stone-800 to-stone-950')
    ;
});

test('exposes a human label per swatch', function (): void {
    expect(TileColor::Grey->label())->toBe('Grey')
        ->and(TileColor::Magenta->label())->toBe('Magenta')
    ;
});

test('random returns a palette member', function (): void {
    expect(TileColor::cases())->toContain(TileColor::random());
});
