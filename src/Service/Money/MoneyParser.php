<?php

// ABOUTME: Locale-aware conversion between a user-entered major-unit money string and minor units.
// ABOUTME: Tolerates grouping separators and currency symbols; scales by the currency's fraction digits.

declare(strict_types=1);

namespace App\Service\Money;

use App\Service\Money\Exception\MoneyParseException;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class MoneyParser
{
    public function __construct(
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    /**
     * Parse a major-unit amount the user typed (e.g. "1,234.56", "$10.99", or the German "4.000,34")
     * into minor units for the given currency. Grouping separators and currency symbols are tolerated;
     * the value is scaled by the currency's fraction digits and rounded to the nearest minor unit.
     *
     * @throws MoneyParseException when the input holds no parsable number
     */
    public function toMinor(string $input, int $fractionDigits): int
    {
        $formatter = new \NumberFormatter($this->localeSwitcher->getLocale(), \NumberFormatter::DECIMAL);

        $cleaned = $this->stripToNumeric($input, $formatter);

        if ('' === $cleaned) {
            throw new MoneyParseException(\sprintf('"%s" is not a valid amount.', $input));
        }

        $value = $formatter->parse($cleaned, \NumberFormatter::TYPE_DOUBLE);

        if (false === $value) {
            throw new MoneyParseException(\sprintf('"%s" is not a valid amount.', $input));
        }

        return (int) round($value * (10 ** $fractionDigits));
    }

    /**
     * Render minor units back to a plain major-unit string (no grouping) for prefilling an input,
     * e.g. 3550 with 2 fraction digits -> "35.50". The result round-trips through toMinor().
     */
    public function toMajorString(int $minorAmount, int $fractionDigits): string
    {
        $formatter = new \NumberFormatter($this->localeSwitcher->getLocale(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $fractionDigits);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $fractionDigits);

        return (string) $formatter->format($minorAmount / (10 ** $fractionDigits));
    }

    /**
     * Drop everything that is not a digit, a sign, or this locale's decimal/grouping separator,
     * so currency symbols and stray whitespace do not defeat the numeric parse.
     */
    private function stripToNumeric(string $input, \NumberFormatter $formatter): string
    {
        $decimal = (string) $formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $grouping = (string) $formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);

        $allowed = '0-9\-' . preg_quote($decimal, '/') . preg_quote($grouping, '/');

        return preg_replace('/[^' . $allowed . ']/u', '', $input) ?? '';
    }
}
