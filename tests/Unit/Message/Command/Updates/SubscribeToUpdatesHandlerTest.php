<?php

// ABOUTME: Unit tests for SubscribeToUpdatesHandler - the landing updates-signup no-op seam.
// ABOUTME: Asserts the interest is logged with the submitted email (the future mailing-list hook point).

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Updates;

use App\Message\Command\Updates\SubscribeToUpdatesCommand;
use App\Message\Command\Updates\SubscribeToUpdatesHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SubscribeToUpdatesHandlerTest extends TestCase
{
    public function testLogsTheSubmittedEmailAsInterest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(self::anything(), ['email' => 'curious@dev88.test'])
        ;

        $handler = new SubscribeToUpdatesHandler($logger);
        $handler(new SubscribeToUpdatesCommand(email: 'curious@dev88.test'));
    }
}
