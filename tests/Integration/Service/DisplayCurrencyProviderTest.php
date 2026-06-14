<?php

// ABOUTME: Integration test that DisplayCurrencyProvider is injectable and reads app.display_currency.

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Enum\Currency;
use App\Service\DisplayCurrencyProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DisplayCurrencyProviderTest extends WebTestCase
{
    public function testIsWiredFromTheAppDisplayCurrencyParameterDefaultingToUsd(): void
    {
        self::createClient();
        $provider = self::getContainer()->get(DisplayCurrencyProvider::class);

        self::assertInstanceOf(DisplayCurrencyProvider::class, $provider);
        self::assertSame(Currency::USD, $provider->get());
    }
}
