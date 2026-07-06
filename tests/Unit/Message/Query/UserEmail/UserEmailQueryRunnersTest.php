<?php

// ABOUTME: Unit tests for the user-email query runners - list-for-user, owner-scoped lookup, by-id lookup.
// ABOUTME: Mocks the repository; asserts each runner delegates to the matching finder.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\UserEmail;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Message\Query\UserEmail\FindEmailForOwnerQuery;
use App\Message\Query\UserEmail\FindEmailForOwnerRunner;
use App\Message\Query\UserEmail\FindEmailsForUserQuery;
use App\Message\Query\UserEmail\FindEmailsForUserRunner;
use App\Message\Query\UserEmail\FindUserEmailByIdQuery;
use App\Message\Query\UserEmail\FindUserEmailByIdRunner;
use App\Repository\UserEmailRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UserEmailQueryRunnersTest extends TestCase
{
    public function testFindEmailsForUserReturnsTheOwnersRows(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $rows = [self::primaryOf($user)];

        $repository = $this->createMock(UserEmailRepository::class);
        $repository->expects(self::once())->method('findForOwnerId')
            ->with($user->id)
            ->willReturn($rows)
        ;

        $runner = new FindEmailsForUserRunner($repository);

        self::assertSame($rows, $runner(new FindEmailsForUserQuery(ownerUserId: $user->id)));
    }

    public function testFindEmailForOwnerReturnsTheScopedRowOrNull(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $row = self::primaryOf($user);

        $repository = $this->createMock(UserEmailRepository::class);
        $repository->expects(self::once())->method('findForOwner')
            ->with($row->id, $user->id)
            ->willReturn($row)
        ;

        $runner = new FindEmailForOwnerRunner($repository);

        self::assertSame($row, $runner(new FindEmailForOwnerQuery(ownerUserId: $user->id, userEmailId: $row->id)));
    }

    public function testFindEmailForOwnerReturnsNullCrossOwner(): void
    {
        $repository = $this->createMock(UserEmailRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn(null);

        $runner = new FindEmailForOwnerRunner($repository);

        self::assertNull($runner(new FindEmailForOwnerQuery(ownerUserId: new Ulid(), userEmailId: new Ulid())));
    }

    public function testFindUserEmailByIdLooksUpWithoutOwnerScope(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $row = self::primaryOf($user);

        $repository = $this->createMock(UserEmailRepository::class);
        $repository->expects(self::once())->method('find')
            ->with($row->id)
            ->willReturn($row)
        ;

        $runner = new FindUserEmailByIdRunner($repository);

        self::assertSame($row, $runner(new FindUserEmailByIdQuery(userEmailId: $row->id)));
    }

    private static function primaryOf(User $user): UserEmail
    {
        // The User constructor already created its primary; return it.
        $primary = $user->emails->first();
        self::assertInstanceOf(UserEmail::class, $primary);

        return $primary;
    }
}
