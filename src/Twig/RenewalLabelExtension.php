<?php

// ABOUTME: Twig extension labeling a subscription's next-renewal line by calendar day in the owner's zone.
// ABOUTME: Today/Tomorrow for the near term (clock-driven, time-of-day independent), else KnpTime's time_diff.

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\ValueObject\CalendarDate;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class RenewalLabelExtension extends AbstractExtension
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
        private readonly DateTimeFormatter $dateTimeFormatter,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('renewal_label', $this->label(...)),
        ];
    }

    /**
     * The renewal date is compared by calendar day against "today" in the owner's timezone so the label
     * does not flip with the time of day and lands on the owner's local calendar: a renewal anchored at
     * midnight today reads "Today" whether the page loads at 1am or 11pm, and a user behind UTC is not
     * told "Tomorrow" while it is still today for them (see ADR-0016). Two or more days out (or any past
     * date) falls back to KnpTime's time_diff, fed the same midnight-aligned days so its count is the
     * calendar-day distance (the renewal day itself counts, since it is a valid not-late payment day)
     * rather than the time-of-day-truncated wall-clock gap.
     */
    public function label(CalendarDate $renewal, User $owner): string
    {
        // Compare as pure calendar dates: the owner's local today (ADR-0016) against the renewal date.
        // The day count is a clean calendar-day integer, keeping the label time-of-day independent.
        $today = $owner->localDateFor($this->clock->now());
        $days = $today->daysUntil($renewal);

        return match ($days) {
            0 => $this->translator->trans('common.relative.today'),
            1 => $this->translator->trans('common.relative.tomorrow'),
            // KnpTime's time_diff needs instants; feed both at UTC midnight so it counts calendar days.
            default => $this->dateTimeFormatter->formatDiff(
                $renewal->toDateTimeImmutable(new \DateTimeZone('UTC')),
                $today->toDateTimeImmutable(new \DateTimeZone('UTC')),
            ),
        };
    }
}
