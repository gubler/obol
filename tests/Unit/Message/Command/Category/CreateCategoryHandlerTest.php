<?php

// ABOUTME: Unit tests for CreateCategoryHandler verifying category creation with color and icon.
// ABOUTME: Mocks the entity manager and asserts the persisted category carries the chosen color/icon.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Category;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Message\Command\Category\CreateCategoryCommand;
use App\Message\Command\Category\CreateCategoryHandler;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreateCategoryHandlerTest extends TestCase
{
    public function testPersistsACategoryWithTheChosenNameColorAndIconOwnedByTheCommandUser(): void
    {
        $owner = new User(email: 'owner@example.com');

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')->willReturn($owner);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Category $category): bool => 'Streaming' === $category->name
                && TileColor::Violet === $category->color
                && CategoryIcon::Tv === $category->icon
                && $owner === $category->owner))
        ;

        $handler = new CreateCategoryHandler($userRepository, $entityManager);
        $handler(new CreateCategoryCommand(ownerUserId: new Ulid(), name: 'Streaming', color: TileColor::Violet, icon: CategoryIcon::Tv));
    }
}
