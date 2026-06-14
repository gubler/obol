<?php

// ABOUTME: Unit tests for CategoryFactory ensuring proper factory defaults and customization.
// ABOUTME: Tests verify category creation with generated names, uniqueness, and custom overrides.

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CategoryFactoryTest extends KernelTestCase
{
    public function testCreatesCategoryWithGeneratedName(): void
    {
        $category = CategoryFactory::createOne();

        self::assertNotEmpty($category->name);
    }

    public function testCreatesMultipleCategoriesWithUniqueNames(): void
    {
        $categories = CategoryFactory::createMany(3);

        $names = array_map(fn (Category $cat): string => $cat->name, $categories);
        self::assertCount(3, array_unique($names));
    }

    public function testAllowsCustomName(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Custom Category']);

        self::assertSame('Custom Category', $category->name);
    }
}
