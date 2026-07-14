<?php

// ABOUTME: Feature test for the app:user:admin console command - grants/revokes ROLE_ADMIN on an account.
// ABOUTME: Covers explicit grant/revoke, idempotency, the exactly-one-flag rule, unknown email, last-admin guard.

declare(strict_types=1);

namespace App\Tests\Feature\Command\User;

use App\Command\UserAdminCommand;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Ulid;

final class UserAdminCommandTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::createClient();
    }

    public function testGrantAddsTheAdminRole(): void
    {
        $user = UserFactory::createOne(['email' => 'tester@dev88.test', 'roles' => []]);

        $exitCode = $this->tester()->execute(['email' => 'tester@dev88.test', '--grant' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertContains('ROLE_ADMIN', $this->reload($user->id)->getRoles());
    }

    public function testGrantingIsIdempotent(): void
    {
        $user = UserFactory::createOne(['email' => 'admin@dev88.test', 'roles' => ['ROLE_ADMIN']]);

        $exitCode = $this->tester()->execute(['email' => 'admin@dev88.test', '--grant' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        // The stored roles array carries ROLE_ADMIN exactly once - no duplicate is appended.
        self::assertSame(['ROLE_ADMIN'], $this->reload($user->id)->roles);
    }

    public function testRevokeRemovesTheAdminRoleWhenAnotherAdminRemains(): void
    {
        // A second admin exists (this one plus the seeded founder), so revoking is allowed.
        $user = UserFactory::createOne(['email' => 'admin@dev88.test', 'roles' => ['ROLE_ADMIN']]);

        $exitCode = $this->tester()->execute(['email' => 'admin@dev88.test', '--revoke' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotContains('ROLE_ADMIN', $this->reload($user->id)->getRoles());
    }

    public function testRequiresExactlyOneOfGrantOrRevoke(): void
    {
        UserFactory::createOne(['email' => 'tester@dev88.test', 'roles' => []]);

        // Neither flag.
        $neither = $this->tester();
        self::assertSame(Command::INVALID, $neither->execute(['email' => 'tester@dev88.test']));
        self::assertStringContainsString('exactly one', $neither->getDisplay());

        // Both flags.
        $both = $this->tester();
        self::assertSame(
            Command::INVALID,
            $both->execute(['email' => 'tester@dev88.test', '--grant' => true, '--revoke' => true]),
        );
    }

    public function testUnknownEmailFailsCleanlyAndChangesNothing(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute(['email' => 'ghost@dev88.test', '--grant' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('No user', $tester->getDisplay());
    }

    public function testCannotRevokeTheLastRemainingAdmin(): void
    {
        $userRepository = self::getContainer()->get(UserRepository::class);

        // Reduce to a single admin whatever the baseline: revoking is allowed while others remain, so
        // strip every admin but the first. The last one left is then the one the guard must protect.
        $admins = $userRepository->findAdmins();
        self::assertNotEmpty($admins);
        $lastAdmin = $admins[0];
        foreach (\array_slice($admins, 1) as $other) {
            self::assertSame(
                Command::SUCCESS,
                $this->tester()->execute(['email' => $other->email, '--revoke' => true]),
            );
        }

        self::assertSame(1, $userRepository->countAdmins());

        $tester = $this->tester();
        $exitCode = $tester->execute(['email' => $lastAdmin->email, '--revoke' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('at least one admin', $tester->getDisplay());
        // Nothing changed: the last admin still holds the role.
        self::assertContains('ROLE_ADMIN', $this->reload($lastAdmin->id)->getRoles());
        self::assertSame(1, $userRepository->countAdmins());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(self::getContainer()->get(UserAdminCommand::class));
    }

    private function reload(Ulid $id): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return self::getContainer()->get(UserRepository::class)->getForId($id);
    }
}
