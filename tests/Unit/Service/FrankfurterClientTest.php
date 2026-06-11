<?php

// ABOUTME: Unit tests for FrankfurterClient parsing the latest EUR-pivot rates.
// ABOUTME: Runs against a recorded response (MockHttpClient), never the live API.

declare(strict_types=1);

use App\Service\FrankfurterClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('parses the latest rates, keeps supported currencies, and pins EUR to 1', function (): void {
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

    $snapshot = (new FrankfurterClient($http))->fetchLatest();

    expect($snapshot->date)->toEqual(new DateTimeImmutable('2024-06-10'))
        ->and($snapshot->rates['USD'])->toBe(1.0732)
        ->and($snapshot->rates['JPY'])->toBe(169.45)
        ->and($snapshot->rates['EUR'])->toBe(1.0)
        ->and($snapshot->rates)->not->toHaveKey('XYZ')
        ->and($snapshot->rates)->toHaveCount(4)
    ;
});
