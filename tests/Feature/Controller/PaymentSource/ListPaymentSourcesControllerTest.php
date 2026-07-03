<?php

// ABOUTME: Feature tests for ListPaymentSourcesController verifying the index listing and empty state.
// ABOUTME: Tests the empty state message and that created sources render in the table.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Factory\PaymentSourceFactory;
use App\Tests\Support\AuthenticatedTestCase;

final class ListPaymentSourcesControllerTest extends AuthenticatedTestCase
{
    public function testShowsEmptyStateWhenNoPaymentSources(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.empty-state', text: 'No payment sources found');
    }

    public function testListsPaymentSources(): void
    {
        $client = $this->authenticatedClient();

        PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        PaymentSourceFactory::createOne(['name' => 'Visa 5678']);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'body', text: 'Amex 1234');
        self::assertSelectorTextContains(selector: 'body', text: 'Visa 5678');
    }
}
