<?php

// ABOUTME: Feature tests for the obligations-over-time trend on /reports and its day/week/month toggle.
// ABOUTME: Verifies the trend section renders its own line chart and that the selected granularity is marked active.

declare(strict_types=1);

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;

test('the reports page shows the obligation trend with a day/week/month toggle, monthly by default', function (): void {
    $client = $this->createClient();
    // Creating a subscription records an obligation snapshot, so the trend has data to plot.
    $category = CategoryFactory::createOne(['name' => 'Streaming']);
    SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

    $client->request(method: 'GET', uri: '/reports');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: '.obligation-trend canvas');                 // the line chart
    $this->assertSelectorExists(selector: 'a[href="/reports?trend=day"]');
    $this->assertSelectorExists(selector: 'a[href="/reports?trend=week"]');
    $this->assertSelectorExists(selector: 'a[href="/reports?trend=month"]');
    $this->assertSelectorTextContains(selector: '.obligation-trend [aria-current="page"]', text: 'Monthly');
});

test('selecting a trend granularity marks it active', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/reports?trend=week');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: '.obligation-trend [aria-current="page"]', text: 'Weekly');
});
