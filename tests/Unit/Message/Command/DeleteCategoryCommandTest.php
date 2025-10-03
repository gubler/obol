<?php

// ABOUTME: Unit tests for DeleteCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with category ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Message\Command\DeleteCategoryCommand;
use PHPUnit\Framework\TestCase;

class DeleteCategoryCommandTest extends TestCase
{
    public function testCreatesCommandWithCategoryId(): void
    {
        $categoryId = '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z';
        $command = new DeleteCategoryCommand(categoryId: $categoryId);

        self::assertSame($categoryId, $command->categoryId);
    }

    public function testIsReadonly(): void
    {
        $command = new DeleteCategoryCommand(
            categoryId: '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z'
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}