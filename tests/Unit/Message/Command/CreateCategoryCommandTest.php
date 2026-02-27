<?php

// ABOUTME: Unit tests for CreateCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with valid data and maintains readonly properties.

declare(strict_types=1);

use App\Message\Command\Category\CreateCategoryCommand;

test('creates command with name', function (): void {
    $command = new CreateCategoryCommand(name: 'Entertainment');

    expect($command->name)->toBe('Entertainment');
});

test('is readonly', function (): void {
    $command = new CreateCategoryCommand(name: 'Utilities');

    $reflection = new ReflectionClass($command);
    expect($reflection->isReadOnly())->toBeTrue();
});
