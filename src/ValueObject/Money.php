<?php

// ABOUTME: Immutable money value object - an integer amount in a currency's minor units plus its Currency.
// ABOUTME: Arithmetic is same-currency only; cross-currency conversion goes through the Converter (#126).

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\Currency;
use Assert\Assertion;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Money
{
    public function __construct(
        #[ORM\Column(name: 'amount', type: 'integer')]
        public int $minorAmount,
        #[ORM\Column(name: 'currency', enumType: Currency::class)]
        public Currency $currency,
    ) {
    }

    public function add(self $other): self
    {
        Assertion::true(
            $this->currency === $other->currency,
            'Cannot add Money of different currencies',
        );

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->minorAmount === $other->minorAmount && $this->currency === $other->currency;
    }

    /**
     * A localized currency rendering via ICU - e.g. en "$15.99"/"¥2,000", de_DE "1.599,99 $".
     * Defaults to the request locale (\Locale::getDefault(), always en today), matching MoneyParser.
     * Fraction digits follow the currency's stored convention rather than ICU's default, so a
     * zero-decimal currency (JPY, HUF) never gains spurious decimals. See ADR-0012.
     */
    public function format(?string $locale = null): string
    {
        $locale ??= \Locale::getDefault();
        $digits = $this->currency->fractionDigits();

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $digits);

        return (string) $formatter->formatCurrency($this->minorAmount / (10 ** $digits), $this->currency->value);
    }
}
