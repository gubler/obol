<?php

// ABOUTME: Tests the add-secondary handler inserts a pending row and mails a verification link to the new address.
// ABOUTME: An address already on the account, or verified by another user, is a silent no-op (nothing inserted or sent).

declare(strict_types=1);

namespace App\Tests\Feature\Message\Command\UserEmail;

use App\Entity\UserEmail;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Message\Command\UserEmail\AddSecondaryEmailCommand;
use App\Message\Command\UserEmail\AddSecondaryEmailHandler;
use App\Repository\UserEmailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

final class AddSecondaryEmailHandlerTest extends WebTestCase
{
    private AddSecondaryEmailHandler $handler;

    private EntityManagerInterface $entityManager;

    private UserEmailRepository $userEmails;

    private InMemoryTransport $mailTransport;

    protected function setUp(): void
    {
        parent::setUp();

        self::createClient();
        $this->handler = self::getContainer()->get(AddSecondaryEmailHandler::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userEmails = self::getContainer()->get(UserEmailRepository::class);
        $mailTransport = self::getContainer()->get('messenger.transport.mail');
        self::assertInstanceOf(InMemoryTransport::class, $mailTransport);
        $this->mailTransport = $mailTransport;
    }

    public function testAddsAPendingRowAndMailsTheNewAddress(): void
    {
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);

        ($this->handler)(new AddSecondaryEmailCommand(ownerUserId: $user->id, email: 'new@dev88.test'));
        $this->entityManager->flush();

        $row = $this->userEmails->findForUserByEmail($user, 'new@dev88.test');
        self::assertInstanceOf(UserEmail::class, $row);
        self::assertFalse($row->isVerified());
        self::assertFalse($row->isPrimary);

        $this->assertSingleQueuedRecipient('new@dev88.test');
    }

    public function testDoesNothingWhenTheAddressIsAlreadyOnTheAccount(): void
    {
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'existing@dev88.test']);

        ($this->handler)(new AddSecondaryEmailCommand(ownerUserId: $user->id, email: 'existing@dev88.test'));
        $this->entityManager->flush();

        self::assertCount(0, $this->mailTransport->getSent());
        // The primary plus the one pre-existing secondary; the handler added no duplicate third row.
        self::assertCount(2, $this->userEmails->findForOwnerId($user->id));
    }

    public function testDoesNothingWhenTheAddressIsVerifiedByAnotherUser(): void
    {
        $owner = UserFactory::createOne(['email' => 'owner@dev88.test']);
        $rival = UserFactory::createOne(['email' => 'rival@dev88.test']);
        UserEmailFactory::createOne(['user' => $rival, 'email' => 'contested@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);

        ($this->handler)(new AddSecondaryEmailCommand(ownerUserId: $owner->id, email: 'contested@dev88.test'));
        $this->entityManager->flush();

        self::assertCount(0, $this->mailTransport->getSent());
        self::assertNull($this->userEmails->findForUserByEmail($owner, 'contested@dev88.test'));
    }

    private function assertSingleQueuedRecipient(string $expected): void
    {
        $sent = $this->mailTransport->getSent();
        self::assertCount(1, $sent);

        $sendEmailMessage = $sent[0]->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $sendEmailMessage);

        $mail = $sendEmailMessage->getMessage();
        self::assertInstanceOf(Email::class, $mail);
        self::assertSame($expected, $mail->getTo()[0]->getAddress());
    }
}
