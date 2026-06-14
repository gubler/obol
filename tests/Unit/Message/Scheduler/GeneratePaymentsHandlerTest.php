<?php

// ABOUTME: Unit test for the GeneratePaymentsHandler scheduler adapter - it dispatches the work command.
// ABOUTME: The find-and-record work itself lives in GenerateDuePaymentsHandler (tested separately).

declare(strict_types=1);

use App\Lib\Bus\CommandBus;
use App\Message\Command\Payment\GenerateDuePaymentsCommand;
use App\Message\Scheduler\GeneratePaymentsHandler;
use App\Message\Scheduler\GeneratePaymentsMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

test('dispatches the generate-due-payments command and touches no data itself', function (): void {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects($this->once())
        ->method('dispatch')
        ->with($this->isInstanceOf(GenerateDuePaymentsCommand::class))
        ->willReturn(new Envelope(new GenerateDuePaymentsCommand()))
    ;

    (new GeneratePaymentsHandler(new CommandBus($messageBus)))(new GeneratePaymentsMessage());
});
