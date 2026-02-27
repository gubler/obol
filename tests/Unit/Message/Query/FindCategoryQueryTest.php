<?php

// ABOUTME: Unit tests for FindCategoryQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with category ID and maintains readonly properties.

declare(strict_types=1);

use App\Message\Query\Category\FindCategoryQuery;

test('creates query with category id', function (): void {
    $categoryId = '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z';
    $query = new FindCategoryQuery(categoryId: $categoryId);

    expect($query->categoryId)->toBe($categoryId);
});

test('is readonly', function (): void {
    $query = new FindCategoryQuery(
        categoryId: '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z'
    );

    $reflection = new ReflectionClass($query);
    expect($reflection->isReadOnly())->toBeTrue();
});
