<?php

// ABOUTME: Unit tests for the TileColor palette enum and its Tailwind gradient mapping.
// ABOUTME: Verifies the 18 swatches, well-formed gradient classes, labels, and random selection.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\TileColor;
use PHPUnit\Framework\TestCase;

final class TileColorTest extends TestCase
{
    public function testDefinesTheFullPaletteOfEighteenSwatches(): void
    {
        self::assertCount(18, TileColor::cases());
    }

    public function testIsAStringBackedEnum(): void
    {
        self::assertSame('blue', TileColor::Blue->value);
        self::assertSame(TileColor::Charcoal, TileColor::from('charcoal'));
    }

    public function testEverySwatchYieldsAWellFormedTopToBottomGradient(): void
    {
        foreach (TileColor::cases() as $color) {
            self::assertStringStartsWith('bg-linear-to-b ', $color->gradientClasses());
            self::assertStringContainsString('from-', $color->gradientClasses());
            self::assertStringContainsString('to-', $color->gradientClasses());
        }
    }

    public function testEverySwatchHoldsItsLighterToneWithAGradientStop(): void
    {
        // The distribution fix (#191): a `from-<pct>%` stop keeps the lighter tone
        // dominant so small tiles are not visually swamped by the dark bottom end.
        foreach (TileColor::cases() as $color) {
            self::assertMatchesRegularExpression('/\bfrom-\d+%/', $color->gradientClasses());
        }
    }

    public function testMapsSwatchesToTheirChosenTailwindShades(): void
    {
        self::assertSame('bg-linear-to-b from-red-500 from-45% to-red-900', TileColor::Red->gradientClasses());
        self::assertSame('bg-linear-to-b from-fuchsia-600 from-45% to-fuchsia-900', TileColor::Magenta->gradientClasses());
        self::assertSame('bg-linear-to-b from-sky-600 from-45% to-sky-900', TileColor::Cyan->gradientClasses());
        self::assertSame('bg-linear-to-b from-stone-600 from-45% to-stone-900', TileColor::Charcoal->gradientClasses());
    }

    public function testExposesAHumanLabelPerSwatch(): void
    {
        self::assertSame('Grey', TileColor::Grey->label());
        self::assertSame('Magenta', TileColor::Magenta->label());
    }

    public function testRandomReturnsAPaletteMember(): void
    {
        self::assertContains(TileColor::random(), TileColor::cases());
    }
}
