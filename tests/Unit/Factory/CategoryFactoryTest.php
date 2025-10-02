<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Factory\CategoryFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class CategoryFactoryTest extends KernelTestCase
{
    use Factories;

    public function testCreatesCategoryWithGeneratedName(): void
    {
        $category = CategoryFactory::createOne();

        $this->assertIsString($category->name);
        $this->assertNotEmpty($category->name);
    }

    public function testCreatesMultipleCategoriesWithUniqueNames(): void
    {
        $categories = CategoryFactory::createMany(3);

        $names = array_map(fn ($cat) => $cat->name, $categories);
        $this->assertCount(3, array_unique($names));
    }

    public function testAllowsCustomName(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Custom Category']);

        $this->assertSame('Custom Category', $category->name);
    }

    public function testPersistsCategoryToDatabase(): void
    {
        $category = CategoryFactory::createOne();

        $this->assertInstanceOf(\Symfony\Component\Uid\Ulid::class, $category->id);
    }
}
