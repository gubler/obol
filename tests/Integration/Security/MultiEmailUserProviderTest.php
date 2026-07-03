<?php

// ABOUTME: Integration tests for MultiEmailUserProvider - resolves any verified address to its User.
// ABOUTME: Unknown and unverified addresses are indistinguishable (both throw) for enumeration safety.

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\User;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Security\MultiEmailUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class MultiEmailUserProviderTest extends WebTestCase
{
    private MultiEmailUserProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        self::createClient();
        $this->provider = self::getContainer()->get(MultiEmailUserProvider::class);
    }

    public function testResolvesAUserByItsVerifiedPrimaryEmail(): void
    {
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);

        $resolved = $this->provider->loadUserByIdentifier('primary@dev88.test');

        self::assertInstanceOf(User::class, $resolved);
        self::assertTrue($resolved->id->equals($user->id));
    }

    public function testResolvesAUserByAVerifiedSecondaryEmail(): void
    {
        $user = UserFactory::createOne();
        UserEmailFactory::createOne(['user' => $user, 'email' => 'secondary@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);

        $resolved = $this->provider->loadUserByIdentifier('secondary@dev88.test');

        self::assertInstanceOf(User::class, $resolved);
        self::assertTrue($resolved->id->equals($user->id));
    }

    public function testThrowsForAnUnverifiedAddress(): void
    {
        $user = UserFactory::createOne();
        UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'unverified@dev88.test']);

        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('unverified@dev88.test');
    }

    public function testThrowsForAnUnknownAddress(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('nobody@dev88.test');
    }

    public function testRefreshUserReloadsTheSameUser(): void
    {
        $user = UserFactory::createOne(['email' => 'refresh@dev88.test']);

        $refreshed = $this->provider->refreshUser($user);

        self::assertInstanceOf(User::class, $refreshed);
        self::assertTrue($refreshed->id->equals($user->id));
    }

    public function testRefreshUserRejectsAForeignUserClass(): void
    {
        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser(new InMemoryUser('someone', null));
    }
}
