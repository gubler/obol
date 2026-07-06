<?php

// ABOUTME: Unit test for CompleteOnboardingHandler - resolves the user and applies the first-run settings.
// ABOUTME: Mocks the repository; asserts the Ulid lookup drives the completeOnboarding() mutation.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Onboarding;

use App\Entity\User;
use App\Enum\Currency;
use App\Message\Command\Onboarding\CompleteOnboardingCommand;
use App\Message\Command\Onboarding\CompleteOnboardingHandler;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class CompleteOnboardingHandlerTest extends TestCase
{
    public function testResolvesTheUserAndAppliesTheSettings(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())
            ->method('getForId')
            ->with($user->id)
            ->willReturn($user)
        ;

        $handler = new CompleteOnboardingHandler($repository);
        $handler(new CompleteOnboardingCommand(
            ownerUserId: $user->id,
            displayName: 'Magos',
            displayCurrency: Currency::GBP,
            timezone: 'Europe/London',
        ));

        self::assertSame('Magos', $user->displayName);
        self::assertSame(Currency::GBP, $user->displayCurrency);
        self::assertSame('Europe/London', $user->timezone);
        self::assertTrue($user->hasCompletedOnboarding());
    }
}
