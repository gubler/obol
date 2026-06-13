<?php

// ABOUTME: Feature test for the homepage capstone toggle between Global Totals and Remaining-in-period.
// ABOUTME: A renewal on the first of this month leaves exactly one payment owed this month, regardless of run date.

declare(strict_types=1);

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;

test('the capstone defaults to Global Totals and toggles to Remaining', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Software']);
    SubscriptionFactory::createOne([
        'category' => $category,
        'cost' => new Money(5000, Currency::USD),
        'paymentPeriod' => PaymentPeriod::Month,
        'paymentPeriodCount' => 1,
        'nextRenewal' => new DateTimeImmutable('first day of this month'),
    ]);

    // Default capstone is Global Totals.
    $client->request(method: 'GET', uri: '/');
    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: '.global-total-monthly');

    // Toggle to Remaining: the single renewal due this month leaves $50.00 owed.
    $client->request(method: 'GET', uri: '/?capstone=remaining');
    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: '.remaining-total-monthly', text: '$50.00');
    $this->assertSelectorTextContains(selector: '.remaining-total-monthly', text: 'this month');
});
