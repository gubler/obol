<?php

// ABOUTME: Unit tests for the SubscriptionEventType enum's translation-key label().
// ABOUTME: label() returns a snake_case `messages` catalog key per ADR-0012, never raw English.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\SubscriptionEventType;
use PHPUnit\Framework\TestCase;

final class SubscriptionEventTypeTest extends TestCase
{
    public function testReturnsATranslationKeyPerType(): void
    {
        self::assertSame('enum.subscription_event_type.cost_change', SubscriptionEventType::CostChange->label());
        self::assertSame('enum.subscription_event_type.update', SubscriptionEventType::Update->label());
        self::assertSame('enum.subscription_event_type.archive', SubscriptionEventType::Archive->label());
        self::assertSame('enum.subscription_event_type.unarchive', SubscriptionEventType::Unarchive->label());
    }
}
