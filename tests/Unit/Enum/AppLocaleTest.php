<?php

// ABOUTME: Unit tests for the AppLocale enum - the languages Obol ships a catalog for, offered in the picker.
// ABOUTME: Each case value is a BCP-47 tag matching a stored User.locale; label() is a translation key.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\AppLocale;
use PHPUnit\Framework\TestCase;

final class AppLocaleTest extends TestCase
{
    public function testCaseValuesAreBcp47Tags(): void
    {
        self::assertSame('en-US', AppLocale::EnUs->value);
        self::assertSame('en-GB', AppLocale::EnGb->value);
        self::assertSame('en-CA', AppLocale::EnCa->value);
    }

    public function testReturnsATranslationKeyPerCase(): void
    {
        self::assertSame('enum.locale.en_us', AppLocale::EnUs->label());
        self::assertSame('enum.locale.en_gb', AppLocale::EnGb->label());
        self::assertSame('enum.locale.en_ca', AppLocale::EnCa->label());
    }

    public function testTryFromMatchesAStoredLocaleTag(): void
    {
        self::assertSame(AppLocale::EnGb, AppLocale::tryFrom('en-GB'));
        // A locale with no shipped catalog (e.g. a browser-guessed region) has no picker case.
        self::assertNull(AppLocale::tryFrom('de-DE'));
    }
}
