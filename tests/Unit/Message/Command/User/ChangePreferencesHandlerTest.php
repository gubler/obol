<?php

// ABOUTME: Unit test for ChangePreferencesHandler - resolves the user by Ulid and applies name + settings.
// ABOUTME: Mocks the repository; asserts the lookup drives the display-name and preferences mutations.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\User;

use App\Entity\User;
use App\Enum\Currency;
use App\Enum\DateFormat;
use App\Message\Command\User\ChangePreferencesCommand;
use App\Message\Command\User\ChangePreferencesHandler;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class ChangePreferencesHandlerTest extends TestCase
{
    public function testResolvesTheUserAndAppliesTheNameAndPreferences(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())
            ->method('getForId')
            ->with($user->id)
            ->willReturn($user)
        ;

        $handler = new ChangePreferencesHandler($repository);
        $handler(new ChangePreferencesCommand(
            ownerUserId: $user->id,
            displayName: 'Magos',
            displayCurrency: Currency::GBP,
            timezone: 'Europe/London',
            locale: 'en-GB',
            dateFormat: DateFormat::Short,
        ));

        self::assertSame('Magos', $user->displayName);
        self::assertSame(Currency::GBP, $user->displayCurrency);
        self::assertSame('Europe/London', $user->timezone);
        self::assertSame('en-GB', $user->locale);
        self::assertSame(DateFormat::Short, $user->dateFormat);
    }
}
