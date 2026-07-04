<?php

// ABOUTME: Unit tests for Category entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid category creation, property initialization, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testCreatesCategoryWithValidName(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Entertainment');

        self::assertSame('Entertainment', $category->name);
    }

    public function testBelongsToItsOwner(): void
    {
        $owner = new User(email: 'owner@example.com');
        $category = new Category(owner: $owner, name: 'Entertainment');

        self::assertSame($owner, $category->owner);
    }

    public function testCreatesCategoryWithAChosenColorAndIcon(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Streaming', color: TileColor::Violet, icon: CategoryIcon::Tv);

        self::assertSame(TileColor::Violet, $category->color);
        self::assertSame(CategoryIcon::Tv, $category->icon);
    }

    public function testDefaultsToARandomColorAndTheNeutralTagIcon(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Software');

        self::assertContains($category->color, TileColor::cases());
        self::assertSame(CategoryIcon::Tag, $category->icon);
    }

    public function testUpdatesNameColorAndIcon(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Original', color: TileColor::Red, icon: CategoryIcon::Tag);

        $category->update(name: '  Streaming  ', color: TileColor::Teal, icon: CategoryIcon::Film);

        self::assertSame('Streaming', $category->name);
        self::assertSame(TileColor::Teal, $category->color);
        self::assertSame(CategoryIcon::Film, $category->icon);
    }

    public function testInitializesEmptySubscriptionsCollection(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Software');

        self::assertCount(0, $category->subscriptions);
    }

    public function testUpdateChangesOnlyTheProvidedFields(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Original', color: TileColor::Red, icon: CategoryIcon::Tag);

        $category->update(icon: CategoryIcon::Film);

        self::assertSame('Original', $category->name);
        self::assertSame(TileColor::Red, $category->color);
        self::assertSame(CategoryIcon::Film, $category->icon);
    }

    public function testUpdateTrimsTheNameWhenProvided(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Original');

        $category->update(name: '  Updated  ');

        self::assertSame('Updated', $category->name);
    }

    public function testUpdateRejectsWhenNoFieldIsProvided(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Valid');

        $this->expectException(\Assert\InvalidArgumentException::class);

        $category->update();
    }

    public function testUpdateRejectsAnEmptyName(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Valid');

        $this->expectException(\Assert\InvalidArgumentException::class);

        $category->update(name: '');
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Category(owner: new User(email: 'owner@example.com'), name: '');
    }

    public function testRejectsWhitespaceName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Category(owner: new User(email: 'owner@example.com'), name: '   ');
    }
}
