<?php

// ABOUTME: Unit tests for the PaymentType enum's translation-key label().
// ABOUTME: label() returns a `messages` catalog key per ADR-0012, never raw English.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\PaymentType;
use PHPUnit\Framework\TestCase;

final class PaymentTypeTest extends TestCase
{
    public function testReturnsATranslationKeyPerType(): void
    {
        self::assertSame('enum.payment_type.verified', PaymentType::Verified->label());
        self::assertSame('enum.payment_type.generated', PaymentType::Generated->label());
    }
}
