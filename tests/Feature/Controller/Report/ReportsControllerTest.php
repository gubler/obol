<?php

// ABOUTME: Feature tests for the /reports page and its category drill-down.
// ABOUTME: Covers the category-composition overview (archived excluded), drill-down, and a 404 for an unknown category.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Report;

use App\Enum\CategoryIcon;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\TranslationAssertions;
use App\ValueObject\Money;
use Symfony\Component\Uid\Ulid;

final class ReportsControllerTest extends AuthenticatedTestCase
{
    use TranslationAssertions;

    public function testReportsOverviewShowsPerCategoryCompositionExcludingArchivedSubscriptions(): void
    {
        $client = $this->authenticatedClient();

        $streaming = CategoryFactory::createOne(['name' => 'Streaming']);
        $software = CategoryFactory::createOne(['name' => 'Software']);
        $defunct = CategoryFactory::createOne(['name' => 'Defunct']);

        SubscriptionFactory::createOne(['category' => $streaming, 'name' => 'Netflix', 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::createOne(['category' => $software, 'name' => 'JetBrains', 'cost' => new Money(1500, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        // Both of these are archived, so neither their category nor their cost should surface.
        SubscriptionFactory::new()->archived()->create(['category' => $streaming, 'name' => 'Old Hulu', 'cost' => new Money(9900, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::new()->archived()->create(['category' => $defunct, 'name' => 'Dead App', 'cost' => new Money(5000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'canvas');                                 // the pie is rendered
        self::assertSelectorTextContains(selector: '.report-total', text: '$55.00');    // 40 + 15, archived excluded

        $names = $crawler->filter('.report-category')->each(static fn ($node): string => $node->text());
        self::assertContains('Streaming', $names);
        self::assertContains('Software', $names);
        self::assertNotContains('Defunct', $names);

        // Each category links to its server-navigated drill-down (base32, as path() generates from a Ulid).
        self::assertSelectorExists(selector: \sprintf('a[href="/reports/categories/%s"]', $streaming->id->toBase32()));
    }

    public function testCategoryDrillDownShowsThatCategorySubscriptionsExcludingArchived(): void
    {
        $client = $this->authenticatedClient();

        $streaming = CategoryFactory::createOne(['name' => 'Streaming']);
        SubscriptionFactory::createOne(['category' => $streaming, 'name' => 'Netflix', 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::new()->archived()->create(['category' => $streaming, 'name' => 'Old Hulu', 'cost' => new Money(9900, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports/categories/' . $streaming->id->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'canvas');
        self::assertSelectorTextContains(selector: 'h1', text: 'Streaming');

        $names = $crawler->filter('.report-subscription')->each(static fn ($node): string => $node->text());
        self::assertContains('Netflix', $names);
        self::assertNotContains('Old Hulu', $names);

        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'reports category drill-down');
    }

    public function testCompositionPieAndLegendUseEachCategoryColorAndIcon(): void
    {
        $client = $this->authenticatedClient();
        $streaming = CategoryFactory::createOne(['name' => 'Streaming', 'color' => TileColor::Violet, 'icon' => CategoryIcon::Tv]);
        SubscriptionFactory::createOne(['category' => $streaming, 'name' => 'Netflix', 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::createOne(['category' => null, 'name' => 'Orphan', 'cost' => new Money(1000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports');

        self::assertResponseIsSuccessful();
        // The legend shows each slice's flat color + icon badge.
        $badge = $crawler->filter('.report-category-link .category-badge')->first();
        self::assertStringContainsString('bg-violet-500', (string) $badge->attr('class'));
        self::assertCount(1, $badge->filter('svg'));

        // The pie wedge fills use the category hex (and Charcoal for uncategorized), matching the swatches.
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('#8b5cf6', $content); // violet-500 (Streaming)
        self::assertStringContainsString('#57534e', $content); // stone-600 (Uncategorized / Charcoal)
    }

    public function testReportsOverviewShowsAnUncategorizedSliceLinkedToItsDrillDown(): void
    {
        $client = $this->authenticatedClient();

        $streaming = CategoryFactory::createOne(['name' => 'Streaming']);
        SubscriptionFactory::createOne(['category' => $streaming, 'name' => 'Netflix', 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::createOne(['category' => null, 'name' => 'Orphan', 'cost' => new Money(1000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports');

        self::assertResponseIsSuccessful();
        $names = $crawler->filter('.report-category')->each(static fn ($node): string => $node->text());
        self::assertContains('Uncategorized', $names);
        self::assertSelectorExists(selector: 'a[href="/reports/categories/uncategorized"]');
    }

    public function testUncategorizedDrillDownShowsSubscriptionsWithNoCategoryExcludingArchived(): void
    {
        $client = $this->authenticatedClient();

        $streaming = CategoryFactory::createOne(['name' => 'Streaming']);
        SubscriptionFactory::createOne(['category' => $streaming, 'name' => 'Netflix', 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::createOne(['category' => null, 'name' => 'Orphan', 'cost' => new Money(1000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::new()->archived()->create(['category' => null, 'name' => 'Dead Orphan', 'cost' => new Money(5000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports/categories/uncategorized');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'canvas');
        self::assertSelectorTextContains(selector: 'h1', text: 'Uncategorized');

        $names = $crawler->filter('.report-subscription')->each(static fn ($node): string => $node->text());
        self::assertContains('Orphan', $names);
        self::assertNotContains('Netflix', $names);
        self::assertNotContains('Dead Orphan', $names);
    }

    public function testDrillDownForUnknownCategoryIs404(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports/categories/' . new Ulid()->toRfc4122());

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }
}
