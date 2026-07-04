<?php

// ABOUTME: Unit tests for DeleteCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with category ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Message\Command\Category\DeleteCategoryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class DeleteCategoryCommandTest extends TestCase
{
    public function testCreatesCommandWithOwnerAndCategoryId(): void
    {
        $ownerUserId = new Ulid();
        $categoryId = new Ulid();
        $command = new DeleteCategoryCommand(ownerUserId: $ownerUserId, categoryId: $categoryId);

        self::assertSame($ownerUserId, $command->ownerUserId);
        self::assertSame($categoryId, $command->categoryId);
    }

    public function testIsReadonly(): void
    {
        $command = new DeleteCategoryCommand(
            ownerUserId: new Ulid(),
            categoryId: new Ulid()
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
