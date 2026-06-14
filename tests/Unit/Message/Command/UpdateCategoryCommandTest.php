<?php

// ABOUTME: Unit tests for UpdateCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with category ID and name, maintaining readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Message\Command\Category\UpdateCategoryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UpdateCategoryCommandTest extends TestCase
{
    public function testCreatesCommandWithCategoryIdAndName(): void
    {
        $categoryId = new Ulid();
        $command = new UpdateCategoryCommand(
            categoryId: $categoryId,
            name: 'Updated Name'
        );

        self::assertSame($categoryId, $command->categoryId);
        self::assertSame('Updated Name', $command->name);
    }

    public function testIsReadonly(): void
    {
        $command = new UpdateCategoryCommand(
            categoryId: new Ulid(),
            name: 'Software'
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
