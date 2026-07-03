<?php

// ABOUTME: Feature test for the app:user:create console command - seeds an account with a verified primary email.
// ABOUTME: Confirms the created account is immediately resolvable for login, and duplicates are rejected cleanly.

declare(strict_types=1);

namespace App\Tests\Feature\Command\User;

use App\Command\CreateUserCommand;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Repository\UserEmailRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateUserCommandTest extends WebTestCase
{
    private UserEmailRepository $userEmailRepository;

    protected function setUp(): void
    {
        parent::setUp();

        self::createClient();
        $this->userEmailRepository = self::getContainer()->get(UserEmailRepository::class);
    }

    public function testCreatesAnAccountWithAVerifiedPrimaryEmail(): void
    {
        $tester = new CommandTester(self::getContainer()->get(CreateUserCommand::class));
        $exitCode = $tester->execute(['email' => 'seed@dev88.test']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('seed@dev88.test', $tester->getDisplay());
        self::assertNotNull($this->userEmailRepository->findVerifiedByEmail('seed@dev88.test'));
    }

    public function testRejectsAnEmailThatIsAnExistingPrimary(): void
    {
        // UserFactory gives the user a verified primary UserEmail matching this address.
        UserFactory::createOne(['email' => 'dupe@dev88.test']);

        $this->assertRejected('dupe@dev88.test');
    }

    public function testRejectsAnEmailThatIsAVerifiedSecondaryOfAnotherUser(): void
    {
        $user = UserFactory::createOne(['email' => 'owner@dev88.test']);
        UserEmailFactory::createOne(['user' => $user, 'email' => 'shared@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);

        $this->assertRejected('shared@dev88.test');
    }

    private function assertRejected(string $email): void
    {
        $tester = new CommandTester(self::getContainer()->get(CreateUserCommand::class));
        $exitCode = $tester->execute(['email' => $email]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }
}
