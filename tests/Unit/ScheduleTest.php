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
        $cache = $this->createMock(CacheInterface::class);
        $schedule = new Schedule($cache);

        $result = $schedule->getSchedule();

        self::assertInstanceOf(SymfonySchedule::class, $result);
    }

    public function testScheduleHasRecurringMessagesConfigured(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $schedule = new Schedule($cache);

        $result = $schedule->getSchedule();
        $messages = $result->getRecurringMessages();

        // Daily payment generation plus the daily exchange-rate pull.
        self::assertCount(2, $messages);
    }
}
