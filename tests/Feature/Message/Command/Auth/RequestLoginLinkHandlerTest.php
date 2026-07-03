<?php

// ABOUTME: Tests the magic-link handler queues an email only for verified addresses, to the typed address.
// ABOUTME: Unknown and unverified addresses queue nothing - the silence is what keeps login enumeration-safe.

declare(strict_types=1);

namespace App\Tests\Feature\Message\Command\Auth;

use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Message\Command\Auth\RequestLoginLinkCommand;
use App\Message\Command\Auth\RequestLoginLinkHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

final class RequestLoginLinkHandlerTest extends WebTestCase
{
    private RequestLoginLinkHandler $handler;

    private InMemoryTransport $mailTransport;

    protected function setUp(): void
    {
        parent::setUp();

        self::createClient();
        $this->handler = self::getContainer()->get(RequestLoginLinkHandler::class);
        $mailTransport = self::getContainer()->get('messenger.transport.mail');
        self::assertInstanceOf(InMemoryTransport::class, $mailTransport);
        $this->mailTransport = $mailTransport;
    }

    public function testQueuesAnEmailToAVerifiedAddress(): void
    {
        UserFactory::createOne(['email' => 'verified@dev88.test']);

        ($this->handler)(new RequestLoginLinkCommand(email: 'verified@dev88.test'));

        $this->assertSingleQueuedRecipient('verified@dev88.test');
    }

    public function testQueuesAnEmailToTheVerifiedSecondaryThatWasTyped(): void
    {
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        UserEmailFactory::createOne(['user' => $user, 'email' => 'secondary@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);

        ($this->handler)(new RequestLoginLinkCommand(email: 'secondary@dev88.test'));

        $this->assertSingleQueuedRecipient('secondary@dev88.test');
    }

    public function testSendsNothingForAnUnknownAddress(): void
    {
        ($this->handler)(new RequestLoginLinkCommand(email: 'nobody@dev88.test'));

        self::assertCount(0, $this->mailTransport->getSent());
    }

    public function testSendsNothingForAnUnverifiedAddress(): void
    {
        $user = UserFactory::createOne();
        UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'unverified@dev88.test']);

        ($this->handler)(new RequestLoginLinkCommand(email: 'unverified@dev88.test'));

        self::assertCount(0, $this->mailTransport->getSent());
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
