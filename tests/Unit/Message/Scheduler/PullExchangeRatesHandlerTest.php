<?php

// ABOUTME: Unit test for the PullExchangeRatesHandler scheduler adapter - it dispatches the work command.
// ABOUTME: The fetch-and-store work itself lives in RefreshExchangeRatesHandler (tested separately).

declare(strict_types=1);

namespace App\Tests\Unit\Message\Scheduler;

use App\Lib\Bus\CommandBus;
use App\Message\Command\ExchangeRate\RefreshExchangeRatesCommand;
use App\Message\Scheduler\PullExchangeRatesHandler;
use App\Message\Scheduler\PullExchangeRatesMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class PullExchangeRatesHandlerTest extends TestCase
{
    public function testDispatchesTheRefreshExchangeRatesCommandAndTouchesNoDataItself(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RefreshExchangeRatesCommand::class))
            ->willReturn(new Envelope(new RefreshExchangeRatesCommand()))
        ;

        (new PullExchangeRatesHandler(new CommandBus($messageBus)))(new PullExchangeRatesMessage());
    }
}
