<?php

// ABOUTME: Unit tests for the CategoryIcon enum - the curated, closed set of Lucide category icons.
// ABOUTME: Verifies the string-backed values, the ux-icons name mapping, labels, and random selection.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\CategoryIcon;
use PHPUnit\Framework\TestCase;

final class CategoryIconTest extends TestCase
{
    public function testDefinesACuratedClosedSetOfIcons(): void
    {
        // Closed and append-only (values are persisted); around thirty hand-picked icons.
        self::assertGreaterThanOrEqual(30, \count(CategoryIcon::cases()));
    }

    public function testIsAStringBackedEnumKeyedByLucideName(): void
    {
        self::assertSame('tv', CategoryIcon::Tv->value);
        self::assertSame('gamepad-2', CategoryIcon::Gamepad2->value);
        self::assertSame(CategoryIcon::Tag, CategoryIcon::from('tag'));
    }

    public function testEveryIconMapsToABundledLucideUxIconName(): void
    {
        foreach (CategoryIcon::cases() as $icon) {
            self::assertSame('lucide:' . $icon->value, $icon->iconName());
            // Each value must have a committed SVG bundled via AssetMapper.
            self::assertFileExists(\dirname(__DIR__, 3) . '/assets/icons/lucide/' . $icon->value . '.svg');
        }
    }

    public function testReturnsATranslationKeyPerIcon(): void
    {
        self::assertSame('enum.category_icon.tv', CategoryIcon::Tv->label());
        self::assertSame('enum.category_icon.book_open', CategoryIcon::BookOpen->label());
        self::assertSame('enum.category_icon.gamepad_2', CategoryIcon::Gamepad2->label());
    }

    public function testRandomReturnsAMember(): void
    {
        self::assertContains(CategoryIcon::random(), CategoryIcon::cases());
    }
}
