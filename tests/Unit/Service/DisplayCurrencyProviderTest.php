<?php

// ABOUTME: Unit test for DisplayCurrencyProvider - resolves its configured ISO code to a Currency.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\Currency;
use App\Service\DisplayCurrencyProvider;
use PHPUnit\Framework\TestCase;

final class DisplayCurrencyProviderTest extends TestCase
{
    public function testExposesTheCurrencyForItsConfiguredCode(): void
    {
        self::assertSame(Currency::JPY, new DisplayCurrencyProvider('JPY')->get());
    }
}
