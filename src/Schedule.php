<?php

declare(strict_types=1);

namespace App;

use App\Message\Scheduler\GeneratePaymentsMessage;
use App\Message\Scheduler\PullExchangeRatesMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
readonly class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run
            // Hourly, not daily: each timezone crosses its local midnight on a different UTC hour, so an
            // hourly cadence catches every owner's local renewal rollover within the hour (see ADR-0016).
            ->add(RecurringMessage::every('1 hour', new GeneratePaymentsMessage()))
            ->add(RecurringMessage::every('1 day', new PullExchangeRatesMessage()))
        ;
    }
}
