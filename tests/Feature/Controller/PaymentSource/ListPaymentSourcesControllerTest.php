<?php

// ABOUTME: Feature tests for ListPaymentSourcesController verifying the index listing and empty state.
// ABOUTME: Tests the empty state message and that created sources render in the table.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Factory\PaymentSourceFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ListPaymentSourcesControllerTest extends WebTestCase
{
    public function testShowsEmptyStateWhenNoPaymentSources(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/payment-sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.empty-state', text: 'No payment sources found');
    }

    public function testListsPaymentSources(): void
    {
        $client = self::createClient();

        PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        PaymentSourceFactory::createOne(['name' => 'Visa 5678']);

        $client->request(method: 'GET', uri: '/payment-sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Amex 1234');
        self::assertSelectorTextContains(selector: 'body', text: 'Visa 5678');
    }
}
