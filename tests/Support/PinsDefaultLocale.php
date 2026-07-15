<?php

// ABOUTME: Test trait pinning \Locale::getDefault() for tests that assert locale-formatted output.
// ABOUTME: Money::format() falls back to the ambient default locale, which only a booted kernel sets.

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * `Money::format()` and `MoneyParser` resolve their locale from `\Locale::getDefault()` when the
 * caller passes none (ADR-0012). A request sets that per user (ADR-0017), but a plain `TestCase`
 * boots no kernel, so it inherits the process default - `en_US_POSIX`, which renders `$ 100.00`
 * rather than `$100.00`.
 *
 * A test asserting formatted output through code that calls `format()` with no argument therefore
 * has to pin the locale itself. Prefer passing an explicit locale (`$money->format('en')`) where
 * the call site allows it; use this trait only when the locale is resolved out of reach, such as
 * inside a service under test.
 *
 * The default is restored afterwards so the pin cannot leak into unrelated tests.
 */
trait PinsDefaultLocale
{
    private string $localeBeforePinning;

    #[Before]
    protected function pinDefaultLocale(): void
    {
        $this->localeBeforePinning = \Locale::getDefault();

        \Locale::setDefault('en');
    }

    #[After]
    protected function restoreDefaultLocale(): void
    {
        \Locale::setDefault($this->localeBeforePinning);
    }
}
