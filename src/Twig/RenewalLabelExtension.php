<?php

// ABOUTME: Twig extension labeling a subscription's next-renewal line by calendar day in the owner's zone.
// ABOUTME: Today/Tomorrow for the near term (clock-driven, time-of-day independent), else KnpTime's time_diff.

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
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
    public function label(\DateTimeImmutable $renewal, User $owner): string
    {
        // Compare as pure calendar dates: the owner's local today, and the renewal's stored local date
        // (ADR-0016). Rebuilding both at UTC midnight from their Y-m-d keeps the day diff a clean integer
        // instead of a cross-timezone hour count, and keeps the label time-of-day independent.
        $today = new \DateTimeImmutable($owner->toLocal($this->clock->now())->format('Y-m-d'), new \DateTimeZone('UTC'));
        $renewalDay = new \DateTimeImmutable($renewal->format('Y-m-d'), new \DateTimeZone('UTC'));

        $days = (int) $today->diff($renewalDay)->format('%r%a');

        return match ($days) {
            0 => $this->translator->trans('common.relative.today'),
            1 => $this->translator->trans('common.relative.tomorrow'),
            default => $this->dateTimeFormatter->formatDiff($renewalDay, $today),
        };
    }
}
