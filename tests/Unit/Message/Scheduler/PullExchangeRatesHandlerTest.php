<?php

// ABOUTME: Unit test for the PullExchangeRatesHandler scheduler adapter - it dispatches the work command.
// ABOUTME: The fetch-and-store work itself lives in RefreshExchangeRatesHandler (tested separately).

declare(strict_types=1);

use App\Lib\Bus\CommandBus;
use App\Message\Command\ExchangeRate\RefreshExchangeRatesCommand;
use App\Message\Scheduler\PullExchangeRatesHandler;
use App\Message\Scheduler\PullExchangeRatesMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

test('dispatches the refresh-exchange-rates command and touches no data itself', function (): void {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects($this->once())
        ->method('dispatch')
        ->with($this->isInstanceOf(RefreshExchangeRatesCommand::class))
        ->willReturn(new Envelope(new RefreshExchangeRatesCommand()))
    ;

    (new PullExchangeRatesHandler(new CommandBus($messageBus)))(new PullExchangeRatesMessage());
});
