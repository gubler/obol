<?php

// ABOUTME: Unit tests for CategoryFactory ensuring proper factory defaults and customization.
// ABOUTME: Tests verify category creation with generated names, uniqueness, and custom overrides.

declare(strict_types=1);

use App\Entity\Category;
use App\Factory\CategoryFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

uses(KernelTestCase::class);

test('creates category with generated name', function (): void {
    $category = CategoryFactory::createOne();

    expect($category->name)->not->toBeEmpty();
});

test('creates multiple categories with unique names', function (): void {
    $categories = CategoryFactory::createMany(3);

    $names = array_map(fn (Category $cat): string => $cat->name, $categories);
    expect(array_unique($names))->toHaveCount(3);
});

test('allows custom name', function (): void {
    $category = CategoryFactory::createOne(['name' => 'Custom Category']);

    expect($category->name)->toBe('Custom Category');
});
