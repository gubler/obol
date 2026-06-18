<?php

// ABOUTME: Unit tests for CreateSubscriptionDto covering its form-backing default values.
// ABOUTME: Pins the default payment period so a new subscription pre-selects Month.

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Subscription;

use App\Dto\Subscription\CreateSubscriptionDto;
use App\Enum\PaymentPeriod;
use PHPUnit\Framework\TestCase;

final class CreateSubscriptionDtoTest extends TestCase
{
    public function testDefaultsPaymentPeriodToMonth(): void
    {
        $dto = new CreateSubscriptionDto();

        self::assertSame(PaymentPeriod::Month, $dto->paymentPeriod);
    }

    public function testDefaultsPaymentPeriodCountToOne(): void
    {
        $dto = new CreateSubscriptionDto();

        self::assertSame(1, $dto->paymentPeriodCount);
    }
}
