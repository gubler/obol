<?php

// ABOUTME: Feature test for the homepage capstone toggle between Global Totals and Remaining-in-period.
// ABOUTME: A renewal on the first of this month leaves exactly one payment owed this month, regardless of run date.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;

final class RemainingCapstoneToggleTest extends AuthenticatedTestCase
{
    public function testTheCapstoneDefaultsToGlobalTotalsAndTogglesToRemaining(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Software']);
        SubscriptionFactory::createOne([
            'category' => $category,
            'cost' => new Money(5000, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            'nextRenewal' => CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('first day of this month'), new \DateTimeZone('UTC')),
        ]);

        // Default capstone is Global Totals.
        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '.global-total-monthly');

        // Toggle to Remaining: the single renewal due this month leaves $50.00 owed.
        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app?capstone=remaining');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.remaining-total-monthly', text: '$50.00');
        self::assertSelectorTextContains(selector: '.remaining-total-monthly', text: 'this month');
    }
}
