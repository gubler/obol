<?php

// ABOUTME: Converts a Money amount into another currency using the stored EUR-pivot exchange rates.
// ABOUTME: Cross-multiplies via EUR for any pair; lives in the data-access layer per ADR-0006. See #126.

declare(strict_types=1);

namespace App\Message\Currency;

use App\Enum\Currency;
use App\Repository\ExchangeRateRepository;
use App\ValueObject\Money;
use Assert\Assertion;

final readonly class Converter
{
    public function __construct(
        private ExchangeRateRepository $exchangeRateRepository,
    ) {
    }

    /**
     * Convert `$from` into `$to`. Uses the latest stored rates, or the rates as of `$asOf` for a
     * historical lookup. Same-currency conversion is the identity. Conversion is approximate by
     * design (#126): naive rounding to the target currency's minor unit, no banker's rounding.
     */
    public function convert(Money $from, Currency $to, ?\DateTimeImmutable $asOf = null): Money
    {
        if ($from->currency === $to) {
            return $from;
        }

        $fromRate = $this->exchangeRateRepository->latestRate($from->currency, $asOf);
        $toRate = $this->exchangeRateRepository->latestRate($to, $asOf);

        Assertion::notNull($fromRate, \sprintf('No exchange rate available for %s', $from->currency->value));
        Assertion::notNull($toRate, \sprintf('No exchange rate available for %s', $to->value));

        // Rates are EUR-pivot: "1 EUR = rate units of currency". Take the source amount back to EUR
        // (divide by its rate), then out to the target (multiply by its rate), rescaling between the
        // two currencies' minor-unit precisions.
        $major = $from->minorAmount / (10 ** $from->currency->fractionDigits());
        $convertedMajor = $major / $fromRate * $toRate;
        $minorAmount = (int) round($convertedMajor * (10 ** $to->fractionDigits()));

        return new Money($minorAmount, $to);
    }
}
