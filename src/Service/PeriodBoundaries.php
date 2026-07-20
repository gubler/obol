<?php

// ABOUTME: Computes the first or last calendar day of the week / month / year containing an as-of date.
// ABOUTME: Works in pure calendar dates (no time sentinel); the week-start day is injected (app.week_start_day).

declare(strict_types=1);

namespace App\Service;

use App\Enum\ObligationTrendPeriod;
use App\Enum\PaymentPeriod;
use App\ValueObject\CalendarDate;
use Assert\Assertion;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PeriodBoundaries
{
    /**
     * @param int $weekStartDay the day the week starts on as a day-of-week ordinal (0 = Sunday, 1 = Monday)
     */
    public function __construct(
        #[Autowire(param: 'app.week_start_day')]
        private int $weekStartDay,
    ) {
        Assertion::true($weekStartDay >= 0 && $weekStartDay <= 6, 'Week start day must be 0 (Sunday) through 6 (Saturday)');
    }

    /**
     * The last day of the calendar period containing `$asOf` (inclusive).
     */
    public function endOfPeriod(PaymentPeriod $period, CalendarDate $asOf): CalendarDate
    {
        return match ($period) {
            PaymentPeriod::Week => $this->endOfWeek($asOf),
            PaymentPeriod::Month => $asOf->lastDayOfMonth(),
            PaymentPeriod::Year => CalendarDate::for($asOf->year, 12, 31),
        };
    }

    /**
     * The first day of the calendar period containing `$asOf`. Week-start leans on the same swappable
     * week definition as `endOfPeriod`. Used to anchor the obligation trend.
     */
    public function startOfPeriod(ObligationTrendPeriod $period, CalendarDate $asOf): CalendarDate
    {
        return match ($period) {
            ObligationTrendPeriod::Week => $this->startOfWeek($asOf),
            ObligationTrendPeriod::Month => CalendarDate::for($asOf->year, $asOf->month, 1),
            ObligationTrendPeriod::Year => CalendarDate::for($asOf->year, 1, 1),
        };
    }

    private function endOfWeek(CalendarDate $asOf): CalendarDate
    {
        $lastDayOfWeek = ($this->weekStartDay + 6) % 7;
        $daysUntilEnd = ($lastDayOfWeek - $asOf->dayOfWeek() + 7) % 7;

        return $asOf->plusDays($daysUntilEnd);
    }

    private function startOfWeek(CalendarDate $asOf): CalendarDate
    {
        $daysSinceStart = ($asOf->dayOfWeek() - $this->weekStartDay + 7) % 7;

        return $asOf->plusDays(-$daysSinceStart);
    }
}
