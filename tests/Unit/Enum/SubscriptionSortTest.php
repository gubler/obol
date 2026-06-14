<?php

// ABOUTME: Unit tests for the SubscriptionSort enum covering request parsing and labels.
// ABOUTME: Asserts the default fallback and that every case carries a human-readable label.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\SubscriptionSort;
use PHPUnit\Framework\TestCase;

final class SubscriptionSortTest extends TestCase
{
    public function testParsesAKnownSortValue(): void
    {
        self::assertSame(SubscriptionSort::Renewal, SubscriptionSort::fromQuery('renewal'));
        self::assertSame(SubscriptionSort::MonthlyCost, SubscriptionSort::fromQuery('monthly-cost'));
        self::assertSame(SubscriptionSort::Cost, SubscriptionSort::fromQuery('cost'));
        self::assertSame(SubscriptionSort::Name, SubscriptionSort::fromQuery('name'));
    }

    public function testFallsBackToNameForAnUnknownOrMissingValue(): void
    {
        self::assertSame(SubscriptionSort::Name, SubscriptionSort::fromQuery(null));
        self::assertSame(SubscriptionSort::Name, SubscriptionSort::fromQuery(''));
        self::assertSame(SubscriptionSort::Name, SubscriptionSort::fromQuery('bogus'));
    }

    public function testEveryCaseHasALabel(): void
    {
        foreach (SubscriptionSort::cases() as $case) {
            self::assertIsString($case->label());
            self::assertNotSame('', $case->label());
        }
    }
}
