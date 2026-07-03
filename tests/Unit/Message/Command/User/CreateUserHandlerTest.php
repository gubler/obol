<?php

// ABOUTME: Unit tests for CreateUserHandler - persists a User with a primary, verified email.
// ABOUTME: Mocks the entity manager and asserts the created graph satisfies the primary-verified invariant.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\User;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Message\Command\User\CreateUserCommand;
use App\Message\Command\User\CreateUserHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CreateUserHandlerTest extends TestCase
{
    public function testPersistsAUserWithAPrimaryVerifiedEmail(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static function (User $user): bool {
                if ('new@dev88.test' !== $user->email || 1 !== $user->emails->count()) {
                    return false;
                }

                $primary = $user->emails->first();

                return $primary instanceof UserEmail
                    && 'new@dev88.test' === $primary->email
                    && $primary->isPrimary
                    && $primary->isVerified();
            }))
        ;

        $handler = new CreateUserHandler($entityManager);
        $user = $handler(new CreateUserCommand(email: 'new@dev88.test'));

        self::assertSame('new@dev88.test', $user->email);
    }
}
