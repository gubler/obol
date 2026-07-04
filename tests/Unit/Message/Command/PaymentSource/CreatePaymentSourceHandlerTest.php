<?php

// ABOUTME: Unit tests for CreatePaymentSourceHandler verifying creation with comment and color.
// ABOUTME: Mocks the entity manager and asserts the persisted source carries the chosen name/comment/color.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\PaymentSource;

use App\Entity\PaymentSource;
use App\Entity\User;
use App\Enum\TileColor;
use App\Message\Command\PaymentSource\CreatePaymentSourceCommand;
use App\Message\Command\PaymentSource\CreatePaymentSourceHandler;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreatePaymentSourceHandlerTest extends TestCase
{
    public function testPersistsAPaymentSourceWithTheChosenNameCommentAndColorOwnedByTheCommandUser(): void
    {
        $owner = new User(email: 'owner@example.com');

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')->willReturn($owner);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (PaymentSource $source): bool => 'Amex 1234' === $source->name
                && 'reissued' === $source->comment
                && TileColor::Violet === $source->color
                && $owner === $source->owner))
        ;

        $handler = new CreatePaymentSourceHandler($userRepository, $entityManager);
        $handler(new CreatePaymentSourceCommand(ownerUserId: new Ulid(), name: 'Amex 1234', comment: 'reissued', color: TileColor::Violet));
    }
}
