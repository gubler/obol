<?php

// ABOUTME: Unit tests for FrankfurterClient parsing the latest EUR-pivot rates.
// ABOUTME: Runs against a recorded response (MockHttpClient), never the live API.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\FrankfurterClient;
use App\Tests\Support\CalendarDateAssertions;
use App\Tests\Support\InstantAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FrankfurterClientTest extends TestCase
{
    use CalendarDateAssertions;
    use InstantAssertions;

    public function testParsesTheLatestRatesKeepsSupportedCurrenciesAndPinsEurToOne(): void
    {
        $body = json_encode([
            'amount' => 1.0,
            'base' => 'EUR',
            'date' => '2024-06-10',
            // GBP/USD/JPY are supported; XYZ is not and must be dropped. EUR is absent from a
            // base=EUR response and is added back as 1.0 by the client.
            'rates' => ['USD' => 1.0732, 'JPY' => 169.45, 'GBP' => 0.8456, 'XYZ' => 9.99],
        ], \JSON_THROW_ON_ERROR);

        $http = new MockHttpClient(new MockResponse($body, [
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        $snapshot = new FrankfurterClient($http)->fetchLatest();

        self::assertSameDate('2024-06-10', $snapshot->date);
        self::assertSame(1.0732, $snapshot->rates['USD']);
        self::assertSame(169.45, $snapshot->rates['JPY']);
        self::assertSame(1.0, $snapshot->rates['EUR']);
        self::assertArrayNotHasKey('XYZ', $snapshot->rates);
        self::assertCount(4, $snapshot->rates);
    }
}
