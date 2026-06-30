<?php

// ABOUTME: Feature tests for the payment-source report: the index composition section and the drill-downs.
// ABOUTME: Covers the by-source section, a source drill-down, the unassigned drill-down, and a 404.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Report;

use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class PaymentSourceReportTest extends WebTestCase
{
    public function testReportsIndexShowsThePaymentSourceCompositionSection(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.reports-payment-source-composition'));
        $link = $crawler->filter('.reports-payment-source-composition a[href="/reports/payment-sources/' . $source->id . '"]');
        self::assertCount(1, $link);
        self::assertStringContainsString('Amex 1234', $link->text());
    }

    public function testSourceDrillDownListsItsSubscriptions(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports/payment-sources/' . $source->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Amex 1234');
        self::assertStringContainsString('Netflix', $crawler->filter('.report-subscription-list')->text());
    }

    public function testUnassignedDrillDownListsSubscriptionsWithNoSource(): void
    {
        $client = self::createClient();

        SubscriptionFactory::createOne(['name' => 'Orphan']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports/payment-sources/unassigned');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Unassigned');
        self::assertStringContainsString('Orphan', $crawler->filter('.report-subscription-list')->text());
    }

    public function testReturns404ForAnUnknownSource(): void
    {
        $client = self::createClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/reports/payment-sources/' . new Ulid());

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }
}
