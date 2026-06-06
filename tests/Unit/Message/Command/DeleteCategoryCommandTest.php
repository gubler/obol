<?php

// ABOUTME: Unit tests for DeleteCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with category ID and maintains readonly properties.

declare(strict_types=1);

use App\Message\Command\Category\DeleteCategoryCommand;
use Symfony\Component\Uid\Ulid;

test('creates command with category id', function (): void {
    $categoryId = new Ulid();
    $command = new DeleteCategoryCommand(categoryId: $categoryId);

    expect($command->categoryId)->toBe($categoryId);
});

test('is readonly', function (): void {
    $command = new DeleteCategoryCommand(
        categoryId: new Ulid()
    );

    $reflection = new ReflectionClass($command);
    expect($reflection->isReadOnly())->toBeTrue();
});
