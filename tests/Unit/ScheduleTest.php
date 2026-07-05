<?php

// ABOUTME: Unit tests for Schedule verifying scheduler configuration.
// ABOUTME: Tests that schedule returns a valid Schedule instance with recurring messages.

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Schedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Contracts\Cache\CacheInterface;

final class ScheduleTest extends TestCase
{
    public function testScheduleReturnsASymfonyScheduleInstance(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $schedule = new Schedule($cache);

        $result = $schedule->getSchedule();

        self::assertInstanceOf(SymfonySchedule::class, $result);
    }

    public function testScheduleRunsPaymentGenerationHourlyAndTheExchangeRatePullDaily(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $schedule = new Schedule($cache);

        $result = $schedule->getSchedule();
        $messages = $result->getRecurringMessages();

        // Payment generation is hourly so each timezone's local-midnight rollover is caught within the
        // hour (ADR-0016); the exchange-rate pull stays daily.
        $cadences = array_map(static fn (\Symfony\Component\Scheduler\RecurringMessage $message): string => (string) $message->getTrigger(), $messages);
        sort($cadences);

        self::assertSame(['every 1 day', 'every 1 hour'], $cadences);
    }
}
