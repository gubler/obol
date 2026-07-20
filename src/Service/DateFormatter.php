<?php

// ABOUTME: Renders a date in a user's DateFormat style, honoring the ambient locale for the ICU lengths.
// ABOUTME: The single date-formatting seam: used by the user_date filter and the preferences picker.

declare(strict_types=1);

namespace App\Service;

use App\Enum\DateFormat;
use App\ValueObject\CalendarDate;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class DateFormatter
{
    public function __construct(
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function format(\DateTimeInterface $date, DateFormat $format): string
    {
        // Long/Medium/Short defer to the ambient locale via their length; Iso overrides with a fixed
        // pattern (when a pattern is set, IntlDateFormatter ignores the length argument).
        $formatter = new \IntlDateFormatter(
            $this->localeSwitcher->getLocale(),
            $format->length(),
            \IntlDateFormatter::NONE,
            null,
            \IntlDateFormatter::GREGORIAN,
            $format->pattern(),
        );

        return (string) $formatter->format($date);
    }

    /**
     * Render a calendar date in a user's DateFormat style. The ICU formatter is pinned to UTC and fed
     * the date at UTC midnight so the rendered day is exactly the calendar day, independent of the
     * ambient process timezone (a calendar date carries no zone of its own). See ADR-0021.
     */
    public function formatCalendarDate(CalendarDate $date, DateFormat $format): string
    {
        $formatter = new \IntlDateFormatter(
            $this->localeSwitcher->getLocale(),
            $format->length(),
            \IntlDateFormatter::NONE,
            'UTC',
            \IntlDateFormatter::GREGORIAN,
            $format->pattern(),
        );

        return (string) $formatter->format($date->toDateTimeImmutable(new \DateTimeZone('UTC')));
    }

    /**
     * Render a date *and time* in the same style: the locale-aware lengths pair the chosen date length
     * with the locale's own short time (so an American reads 12-hour AM/PM and a Briton 24-hour), while
     * Iso pins a fixed 24-hour pattern. Used by the audit log, the one place a full timestamp shows.
     */
    public function formatDateTime(\DateTimeInterface $dateTime, DateFormat $format): string
    {
        $formatter = new \IntlDateFormatter(
            $this->localeSwitcher->getLocale(),
            $format->length(),
            \IntlDateFormatter::SHORT,
            null,
            \IntlDateFormatter::GREGORIAN,
            $format->dateTimePattern(),
        );

        return (string) $formatter->format($dateTime);
    }
}
