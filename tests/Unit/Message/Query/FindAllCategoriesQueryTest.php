<?php

// ABOUTME: Unit tests for FindAllCategoriesQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates without parameters and maintains readonly properties.

declare(strict_types=1);

use App\Message\Query\Category\FindAllCategoriesQuery;

test('is readonly', function (): void {
    $query = new FindAllCategoriesQuery();

    $reflection = new ReflectionClass($query);
    expect($reflection->isReadOnly())->toBeTrue();
});
