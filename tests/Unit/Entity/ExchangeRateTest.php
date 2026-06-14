<?php

// ABOUTME: Unit tests for the ExchangeRate entity - a EUR-pivot rate for a currency on a date.
// ABOUTME: Verifies construction and the positive-rate invariant.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Tests\Support\InstantAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ExchangeRateTest extends TestCase
{
    use InstantAssertions;

    public function testStoresAEurPivotRateForACurrencyOnADate(): void
    {
        $rate = new ExchangeRate(Currency::USD, 1.0732, new \DateTimeImmutable('2024-06-10'));

        self::assertSame(Currency::USD, $rate->currency);
        self::assertSame(1.0732, $rate->rate);
        self::assertSameInstant(new \DateTimeImmutable('2024-06-10'), $rate->asOf);
        self::assertInstanceOf(Ulid::class, $rate->id);
    }

    public function testRejectsANonPositiveRate(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new ExchangeRate(Currency::USD, 0.0, new \DateTimeImmutable('2024-06-10'));
    }
}
