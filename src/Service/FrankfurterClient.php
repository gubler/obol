<?php

// ABOUTME: Fetches the latest EUR-pivot reference rates from the Frankfurter API (ECB data).
// ABOUTME: Keeps only currencies in the Currency enum and pins EUR to 1.0 (absent in a base=EUR reply).

declare(strict_types=1);

namespace App\Service;

use App\Enum\Currency;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class FrankfurterClient implements ExchangeRateProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        // Defaulted scalar: autowiring keeps the default. Bind this argument in services.yaml to
        // point at a self-hosted Frankfurter instance later.
        private string $baseUri = 'https://api.frankfurter.dev/v1',
    ) {
    }

    public function fetchLatest(): RateSnapshot
    {
        $response = $this->httpClient->request('GET', $this->baseUri . '/latest', [
            'query' => ['base' => 'EUR'],
        ]);

        /** @var array{date: string, rates: array<string, int|float>} $data */
        $data = $response->toArray();

        // EUR is the pivot and never appears in a base=EUR response; record it as 1.0.
        $rates = [Currency::EUR->value => 1.0];
        foreach ($data['rates'] as $code => $rate) {
            $currency = Currency::tryFrom((string) $code);
            if (null !== $currency) {
                $rates[$currency->value] = (float) $rate;
            }
        }

        return new RateSnapshot(new \DateTimeImmutable($data['date']), $rates);
    }
}
