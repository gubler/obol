<?php

// ABOUTME: Feature tests for ShowPaymentSourceController verifying the detail page.
// ABOUTME: Tests rendering of the name and comment, and 404 handling for a missing source.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Factory\PaymentSourceFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class ShowPaymentSourceControllerTest extends WebTestCase
{
    public function testShowsPaymentSourceDetails(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234', 'comment' => 'Primary card']);

        $client->request(method: 'GET', uri: '/payment-sources/' . $source->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Amex 1234');
        self::assertSelectorTextContains(selector: 'body', text: 'Primary card');
    }

    public function testReturns404ForNonExistentPaymentSource(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/payment-sources/' . new Ulid());

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }
}
