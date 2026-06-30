<?php

// ABOUTME: Feature tests for the obligations-over-time trend on /reports and its week/month/year toggle.
// ABOUTME: Verifies the trend section renders its own line chart and that the selected granularity is marked active.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Report;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ObligationTrendTest extends WebTestCase
{
    public function testReportsPageShowsObligationTrendWithWeekMonthYearToggleMonthlyByDefault(): void
    {
        $client = self::createClient();
        // Creating a subscription records an obligation snapshot, so the trend has data to plot.
        $category = CategoryFactory::createOne(['name' => 'Streaming']);
        SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '.obligation-trend canvas');                 // the line chart
        self::assertSelectorExists(selector: 'a[href="/reports?trend=week"]');
        self::assertSelectorExists(selector: 'a[href="/reports?trend=month"]');
        self::assertSelectorExists(selector: 'a[href="/reports?trend=year"]');
        self::assertSelectorTextContains(selector: '.obligation-trend [aria-current="page"]', text: 'Monthly');
    }

    public function testSelectingTrendGranularityMarksItActive(): void
    {
        $client = self::createClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports?trend=week');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.obligation-trend [aria-current="page"]', text: 'Weekly');
    }
}
