<?php

// ABOUTME: Unit tests for Schedule verifying scheduler configuration.
// ABOUTME: Tests that schedule returns a valid Schedule instance with recurring messages.

declare(strict_types=1);

use App\Schedule;
use Symfony\Contracts\Cache\CacheInterface;

test('schedule returns a Symfony Schedule instance', function (): void {
    $cache = $this->createMock(CacheInterface::class);
    $schedule = new Schedule($cache);

    $result = $schedule->getSchedule();

    expect($result)->toBeInstanceOf(\Symfony\Component\Scheduler\Schedule::class);
});

test('schedule has recurring messages configured', function (): void {
    $cache = $this->createMock(CacheInterface::class);
    $schedule = new Schedule($cache);

    $result = $schedule->getSchedule();
    $messages = $result->getRecurringMessages();

    expect($messages)->toHaveCount(1);
});
