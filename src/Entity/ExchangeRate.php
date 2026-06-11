<?php

// ABOUTME: A daily EUR-pivot exchange rate: "1 EUR = rate units of currency" on a given date.
// ABOUTME: Append-only - one row per (currency, date); the converter reads the latest. See #126.

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Repository\ExchangeRateRepository;
use Assert\Assertion;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_exchange_rate_currency_as_of', columns: ['currency', 'as_of'])]
class ExchangeRate
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    public function __construct(
        #[ORM\Column(enumType: Currency::class)]
        public private(set) Currency $currency,
        #[ORM\Column]
        public private(set) float $rate,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        public private(set) \DateTimeImmutable $asOf,
    ) {
        Assertion::greaterThan(value: $rate, limit: 0, message: 'Exchange rate must be greater than zero');

        $this->id = new Ulid();
    }
}
