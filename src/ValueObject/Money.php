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
        #[ORM\Column(name: 'amount', type: \Doctrine\DBAL\Types\Types::INTEGER)]
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
}
