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

    public function testEverySwatchYieldsAFlatSolidBaseColorClass(): void
    {
        // Categories render flat (small icons look bad on a gradient): the bright `from` shade only.
        foreach (TileColor::cases() as $color) {
            self::assertStringStartsWith('bg-', $color->baseColorClass());
            self::assertStringNotContainsString('linear', $color->baseColorClass());
            self::assertStringNotContainsString('from-', $color->baseColorClass());
            self::assertStringNotContainsString(' ', $color->baseColorClass());
        }
    }

    public function testMapsSwatchesToTheirFlatBaseShadeMatchingTheGradientFrom(): void
    {
        self::assertSame('bg-red-500', TileColor::Red->baseColorClass());
        self::assertSame('bg-fuchsia-600', TileColor::Magenta->baseColorClass());
        self::assertSame('bg-sky-600', TileColor::Cyan->baseColorClass());
        self::assertSame('bg-stone-600', TileColor::Charcoal->baseColorClass());
    }

    public function testEverySwatchYieldsAHexForChartFills(): void
    {
        foreach (TileColor::cases() as $color) {
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color->baseColorHex());
        }
    }

    public function testMapsSwatchesToTheBaseShadeHex(): void
    {
        self::assertSame('#ef4444', TileColor::Red->baseColorHex());     // red-500
        self::assertSame('#0d9488', TileColor::Teal->baseColorHex());    // teal-600
        self::assertSame('#57534e', TileColor::Charcoal->baseColorHex()); // stone-600
    }

    public function testReturnsATranslationKeyPerSwatch(): void
    {
        self::assertSame('enum.tile_color.grey', TileColor::Grey->label());
        self::assertSame('enum.tile_color.magenta', TileColor::Magenta->label());
    }

    public function testRandomReturnsAPaletteMember(): void
    {
        self::assertContains(TileColor::random(), TileColor::cases());
    }
}
