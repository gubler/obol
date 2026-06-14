<?php

// ABOUTME: Unit tests for Category entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid category creation, property initialization, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testCreatesCategoryWithValidName(): void
    {
        $category = new Category(name: 'Entertainment');

        self::assertSame('Entertainment', $category->name);
    }

    public function testInitializesEmptySubscriptionsCollection(): void
    {
        $category = new Category(name: 'Software');

        self::assertCount(0, $category->subscriptions);
    }

    public function testAllowsSettingName(): void
    {
        $category = new Category(name: 'Original Name');

        $category->setName('Updated Name');

        self::assertSame('Updated Name', $category->name);
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Category(name: '');
    }

    public function testRejectsWhitespaceName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Category(name: '   ');
    }

    public function testSetNameRejectsEmptyName(): void
    {
        $category = new Category(name: 'Valid');

        $this->expectException(\Assert\InvalidArgumentException::class);

        $category->setName('');
    }

    public function testSetNameRejectsWhitespaceName(): void
    {
        $category = new Category(name: 'Valid');

        $this->expectException(\Assert\InvalidArgumentException::class);

        $category->setName('   ');
    }

    public function testSetNameTrimsName(): void
    {
        $category = new Category(name: 'Original');
        $category->setName('  Updated  ');

        self::assertSame('Updated', $category->name);
    }
}
