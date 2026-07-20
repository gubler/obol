<?php

// ABOUTME: Test trait pinning date_default_timezone_set() for tests that assert ambient-zone independence.
// ABOUTME: A few date paths (Doctrine's DateImmutableType, IntlDateFormatter) read the process default zone.

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * The process default timezone (`date.timezone`, `date_default_timezone_set()`) leaks into a handful of
 * date paths - notably `new \DateTime('today')`, Doctrine's built-in `DateImmutableType`, and an
 * `IntlDateFormatter` built with a null timezone. `CalendarDate` is designed to be independent of it, and
 * a test proving that independence needs to be able to set a hostile ambient zone and restore it.
 *
 * Prefer designing the code under test to not read the ambient zone at all (pass or construct an explicit
 * zone); use this trait only to *prove* that independence, or where the ambient value is genuinely out of
 * reach. The default is restored afterwards so the pin cannot leak into unrelated tests.
 */
trait PinsDefaultTimezone
{
    private string $timezoneBeforePinning;

    #[Before]
    protected function rememberDefaultTimezone(): void
    {
        $this->timezoneBeforePinning = date_default_timezone_get();
    }

    #[After]
    protected function restoreDefaultTimezone(): void
    {
        date_default_timezone_set($this->timezoneBeforePinning);
    }
}
