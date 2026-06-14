<?php

// ABOUTME: Test trait asserting two date-times represent the same instant by value.
// ABOUTME: Replaces Pest's loose `->toEqual($dateTime)` (php-cs-fixer would rewrite a
// ABOUTME: naive assertEquals into identity-comparing assertSame and break value equality).

declare(strict_types=1);

namespace App\Tests\Support;

trait InstantAssertions
{
    private static function assertSameInstant(\DateTimeInterface $expected, \DateTimeInterface $actual): void
    {
        self::assertSame(
            $expected->format('Y-m-d\TH:i:s.uP'),
            $actual->format('Y-m-d\TH:i:s.uP'),
        );
    }
}
