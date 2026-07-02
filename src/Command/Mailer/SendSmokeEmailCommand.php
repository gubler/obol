<?php

// ABOUTME: Console smoke command that sends a plain test email straight through the mailer transport.
// ABOUTME: Sends synchronously (bypassing the async `mail` transport) so a broken DSN surfaces here, not on the worker.

declare(strict_types=1);

namespace App\Command\Mailer;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:mailer:smoke',
    description: 'Send a plain test email through the mailer transport - the pre-flight gate before the founder migration',
)]
final class SendSmokeEmailCommand extends Command
{
    public function __construct(
        // The transport, not MailerInterface: transactional mail is routed to
        // the async `mail` transport (messenger.yaml), but the smoke command must
        // send in-process so a misconfigured DSN throws here instead of queueing
        // and failing silently on the worker.
        private readonly TransportInterface $transport,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('address', InputArgument::REQUIRED, 'Recipient email')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Sender email', 'noreply@dev88.test')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Subject line', 'Obol mailer smoke test')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $to */
        $to = $input->getArgument('address');
        /** @var string $from */
        $from = $input->getOption('from');
        /** @var string $subject */
        $subject = $input->getOption('subject');

        $this->transport->send(
            new Email()
                ->from($from)
                ->to($to)
                ->subject($subject)
                ->text(\sprintf(
                    "If you can read this, the mailer transport is wired correctly.\n\nFrom: %s\nTo: %s",
                    $from,
                    $to,
                ))
        );

        $io->success(\sprintf('Sent: from=%s to=%s subject="%s"', $from, $to, $subject));
        $io->writeln('Check the recipient inbox to confirm delivery.');

        return Command::SUCCESS;
    }
}
