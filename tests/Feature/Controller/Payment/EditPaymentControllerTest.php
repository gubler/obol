<?php

// ABOUTME: Feature tests for EditPaymentController verifying payment amendment via the edit form.
// ABOUTME: Covers prefilled form rendering, amending (which verifies the payment), and 404 handling.

declare(strict_types=1);

use App\Entity\Payment;
use App\Enum\PaymentType;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('get request displays the edit form prefilled with the payment amount', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne(['category' => $category, 'cost' => 1599]);
    $payment = PaymentFactory::createOne([
        'subscription' => $subscription,
        'type' => PaymentType::Generated,
        'amount' => 1599,
        'paidDate' => new DateTimeImmutable('2024-01-01'),
    ]);

    $client->request(method: 'GET', uri: '/payments/' . $payment->id . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'input[name="amend_payment[amount]"][value="1599"]');
});

test('post request amends the payment and verifies it', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne(['category' => $category, 'cost' => 1599]);
    $payment = PaymentFactory::createOne([
        'subscription' => $subscription,
        'type' => PaymentType::Generated,
        'amount' => 1599,
        'paidDate' => new DateTimeImmutable('2024-01-01'),
    ]);

    $crawler = $client->request(method: 'GET', uri: '/payments/' . $payment->id . '/edit');
    $form = $crawler->selectButton(value: 'Save')->form([
        'amend_payment[amount]' => '1299',
        'amend_payment[paidDate]' => '2024-01-05',
    ]);
    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $entityManager->clear();

    $updated = $entityManager->getRepository(Payment::class)->find($payment->id);
    expect($updated)->not->toBeNull();
    expect($updated->amount)->toBe(1299)
        ->and($updated->type)->toBe(PaymentType::Verified)
    ;
});

test('get request with invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});
