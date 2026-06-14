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

    public function testMapsSwatchesToTheirChosenTailwindShades(): void
    {
        self::assertSame('bg-linear-to-b from-red-600 to-red-950', TileColor::Red->gradientClasses());
        self::assertSame('bg-linear-to-b from-fuchsia-500 to-fuchsia-950', TileColor::Magenta->gradientClasses());
        self::assertSame('bg-linear-to-b from-sky-500 to-sky-950', TileColor::Cyan->gradientClasses());
        self::assertSame('bg-linear-to-b from-stone-800 to-stone-950', TileColor::Charcoal->gradientClasses());
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
