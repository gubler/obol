<?php

// ABOUTME: Integration test for the full payment CRUD workflow.
// ABOUTME: Tests creating a subscription, adding a payment, verifying it appears, then deleting it.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Payment;

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaymentCrudWorkflowTest extends WebTestCase
{
    public function testFullPaymentWorkflow(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => 1599,
        ]);

        // Visit subscription show page — no payments yet
        $client->request('GET', '/subscriptions/' . $subscription->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.payments-section', 'No payments recorded');

        // Navigate to create payment form
        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        self::assertResponseIsSuccessful();

        // Submit payment form
        $client->submitForm('Save', [
            'create_payment[amount]' => '1599',
            'create_payment[paidDate]' => '2025-01-15',
        ]);
        self::assertResponseRedirects('/subscriptions/' . $subscription->id);
        $client->followRedirect();

        // Verify payment appears on show page
        self::assertSelectorTextContains('.flash-success', 'Payment recorded successfully');
        self::assertSelectorTextContains('.payments-section', '$15.99');

        // Delete the payment
        $crawler = $client->getCrawler();
        $deleteForm = $crawler->filter('.payment-delete-form')->first()->form();
        $client->submit($deleteForm);

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Payment deleted successfully');
        self::assertSelectorTextContains('.payments-section', 'No payments recorded');
    }
}
