<?php

// ABOUTME: Computes the start or inclusive end of the current calendar day / week / month / year for an as-of date.
// ABOUTME: Used by remaining-in-period and obligation-trend reports; the week-start day is injected (app.week_start_day).

declare(strict_types=1);

namespace App\Service;

use App\Enum\ObligationTrendPeriod;
use App\Enum\PaymentPeriod;
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
     * The last moment of the calendar period containing `$asOf` (inclusive, end of day on the last day).
     */
    public function endOfPeriod(PaymentPeriod $period, \DateTimeImmutable $asOf): \DateTimeImmutable
    {
        return match ($period) {
            PaymentPeriod::Week => $this->endOfWeek($asOf),
            PaymentPeriod::Month => $asOf->modify('last day of this month')->setTime(23, 59, 59),
            PaymentPeriod::Year => $asOf->setDate((int) $asOf->format('Y'), 12, 31)->setTime(23, 59, 59),
        };
    }

    /**
     * The first moment of the calendar period containing `$asOf` (start of day on the first day). Week-start
     * leans on the same swappable week definition as `endOfPeriod`. Used to anchor the obligation trend.
     */
    public function startOfPeriod(ObligationTrendPeriod $period, \DateTimeImmutable $asOf): \DateTimeImmutable
    {
        return match ($period) {
            ObligationTrendPeriod::Day => $asOf->setTime(0, 0),
            ObligationTrendPeriod::Week => $this->startOfWeek($asOf),
            ObligationTrendPeriod::Month => $asOf->modify('first day of this month')->setTime(0, 0),
        };
    }

    private function endOfWeek(\DateTimeImmutable $asOf): \DateTimeImmutable
    {
        $lastDayOfWeek = ($this->weekStartDay + 6) % 7;
        $daysUntilEnd = ($lastDayOfWeek - (int) $asOf->format('w') + 7) % 7;

        return $asOf->modify(\sprintf('+%d days', $daysUntilEnd))->setTime(23, 59, 59);
    }

    private function startOfWeek(\DateTimeImmutable $asOf): \DateTimeImmutable
    {
        $daysSinceStart = ((int) $asOf->format('w') - $this->weekStartDay + 7) % 7;

        return $asOf->modify(\sprintf('-%d days', $daysSinceStart))->setTime(0, 0);
    }
}
