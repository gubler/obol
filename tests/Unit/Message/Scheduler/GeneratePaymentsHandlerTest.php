<?php

// ABOUTME: Unit test for the GeneratePaymentsHandler scheduler adapter - it dispatches the work command.
// ABOUTME: The find-and-record work itself lives in GenerateDuePaymentsHandler (tested separately).

declare(strict_types=1);

namespace App\Tests\Unit\Message\Scheduler;

use App\Lib\Bus\CommandBus;
use App\Message\Command\Payment\GenerateDuePaymentsCommand;
use App\Message\Scheduler\GeneratePaymentsHandler;
use App\Message\Scheduler\GeneratePaymentsMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class GeneratePaymentsHandlerTest extends TestCase
{
    public function testDispatchesTheGenerateDuePaymentsCommandAndTouchesNoDataItself(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GenerateDuePaymentsCommand::class))
            ->willReturn(new Envelope(new GenerateDuePaymentsCommand()))
        ;

        (new GeneratePaymentsHandler(new CommandBus($messageBus)))(new GeneratePaymentsMessage());
    }
}
