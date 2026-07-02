<?php

// ABOUTME: Feature test for app:mailer:smoke - confirms the mailer transport + null:// wiring send in-process.
// ABOUTME: The command must send synchronously (not via the async `mail` transport) so a broken DSN throws here.

declare(strict_types=1);

namespace App\Tests\Feature\Command\Mailer;

use App\Command\Mailer\SendSmokeEmailCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

final class SendSmokeEmailCommandTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testSendsSynchronouslyNotViaTheAsyncMailTransport(): void
    {
        $tester = new CommandTester(self::getContainer()->get(SendSmokeEmailCommand::class));
        $exitCode = $tester->execute(['address' => 'smoke@dev88.test']);

        self::assertSame(0, $exitCode);

        // The send must happen in-process, not be deferred onto the `mail` transport:
        // the smoke command exists to exercise the DSN so a broken one throws here,
        // rather than queueing and failing silently on the worker.
        self::assertEmailCount(1);
        self::assertQueuedEmailCount(0);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('smoke@dev88.test', $email->getTo()[0]->getAddress());
    }

    public function testReportsSuccessAndEchoesTheRecipient(): void
    {
        $tester = new CommandTester(self::getContainer()->get(SendSmokeEmailCommand::class));
        $exitCode = $tester->execute(['address' => 'magos@dev88.test']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Sent', $tester->getDisplay());
        self::assertStringContainsString('magos@dev88.test', $tester->getDisplay());
    }

    public function testAcceptsCustomFromAndSubject(): void
    {
        $tester = new CommandTester(self::getContainer()->get(SendSmokeEmailCommand::class));
        $exitCode = $tester->execute([
            'address' => 'someone@example.test',
            '--from' => 'sender@example.test',
            '--subject' => 'Custom subject',
        ]);

        self::assertSame(0, $exitCode);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('sender@example.test', $email->getFrom()[0]->getAddress());
        self::assertSame('Custom subject', $email->getSubject());
    }
}
