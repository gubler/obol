<?php

// ABOUTME: Tests the two-flush primary swap - the new row becomes primary, the old is demoted, User.email follows.
// ABOUTME: Guards reject promoting an unverified address or the address that is already primary.

declare(strict_types=1);

namespace App\Tests\Feature\Message\Command\UserEmail;

use App\Exception\CannotPromoteUnverifiedEmailException;
use App\Exception\EmailAlreadyPrimaryException;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Message\Command\UserEmail\PromotePrimaryEmailCommand;
use App\Message\Command\UserEmail\PromotePrimaryEmailHandler;
use App\Repository\UserEmailRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PromotePrimaryEmailHandlerTest extends WebTestCase
{
    private PromotePrimaryEmailHandler $handler;

    private EntityManagerInterface $entityManager;

    private UserEmailRepository $userEmails;

    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        self::createClient();
        $this->handler = self::getContainer()->get(PromotePrimaryEmailHandler::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userEmails = self::getContainer()->get(UserEmailRepository::class);
        $this->users = self::getContainer()->get(UserRepository::class);
    }

    public function testPromotingAVerifiedSecondarySwapsThePrimaryAndUpdatesTheCache(): void
    {
        $user = UserFactory::createOne(['email' => 'old@dev88.test']);
        $secondary = UserEmailFactory::createOne(['user' => $user, 'email' => 'new@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);

        ($this->handler)(new PromotePrimaryEmailCommand(ownerUserId: $user->id, userEmailId: $secondary->id));
        $this->entityManager->clear();

        $reloaded = $this->users->getForId($user->id);
        self::assertSame('new@dev88.test', $reloaded->email);

        $rows = $this->userEmails->findForOwnerId($user->id);
        $primaries = array_values(array_filter($rows, static fn (\App\Entity\UserEmail $row): bool => $row->isPrimary));
        self::assertCount(1, $primaries);
        self::assertSame('new@dev88.test', $primaries[0]->email);
    }

    public function testPromotingAnUnverifiedAddressIsRejected(): void
    {
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);

        $this->expectException(CannotPromoteUnverifiedEmailException::class);
        ($this->handler)(new PromotePrimaryEmailCommand(ownerUserId: $user->id, userEmailId: $pending->id));
    }

    public function testPromotingTheCurrentPrimaryIsRejected(): void
    {
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $primary = $this->userEmails->findPrimaryForUser($user);

        $this->expectException(EmailAlreadyPrimaryException::class);
        ($this->handler)(new PromotePrimaryEmailCommand(ownerUserId: $user->id, userEmailId: $primary->id));
    }
}
