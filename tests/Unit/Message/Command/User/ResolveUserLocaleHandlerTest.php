<?php

// ABOUTME: Unit test for ResolveUserLocaleHandler - persists a browser-inferred locale onto the user.
// ABOUTME: Mocks the repository; asserts the Ulid lookup drives the resolveLocale() mutation.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\User;

use App\Entity\User;
use App\Message\Command\User\ResolveUserLocaleCommand;
use App\Message\Command\User\ResolveUserLocaleHandler;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class ResolveUserLocaleHandlerTest extends TestCase
{
    public function testResolvesTheUserAndStoresTheLocale(): void
    {
        $user = new User(email: 'magos@dev88.test');
        self::assertNull($user->locale);

        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())
            ->method('getForId')
            ->with($user->id)
            ->willReturn($user)
        ;

        $handler = new ResolveUserLocaleHandler($repository);
        $handler(new ResolveUserLocaleCommand(ownerUserId: $user->id, locale: 'de-DE'));

        self::assertSame('de-DE', $user->locale);
    }
}
