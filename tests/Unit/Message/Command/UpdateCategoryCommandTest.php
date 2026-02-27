<?php

// ABOUTME: Unit tests for UpdateCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with category ID and name, maintaining readonly properties.

declare(strict_types=1);

use App\Message\Command\Category\UpdateCategoryCommand;

test('creates command with category id and name', function (): void {
    $categoryId = '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z';
    $command = new UpdateCategoryCommand(
        categoryId: $categoryId,
        name: 'Updated Name'
    );

    expect($command->categoryId)->toBe($categoryId)
        ->and($command->name)->toBe('Updated Name')
    ;
});

test('is readonly', function (): void {
    $command = new UpdateCategoryCommand(
        categoryId: '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z',
        name: 'Software'
    );

    $reflection = new ReflectionClass($command);
    expect($reflection->isReadOnly())->toBeTrue();
});
