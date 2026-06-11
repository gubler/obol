<?php

// ABOUTME: Feature tests for ValidatePaymentController verifying one-click confirmation of generated payments.
// ABOUTME: Validating flips a Generated payment to Verified, leaving the amount unchanged.

declare(strict_types=1);

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentType;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

test('post request validates a generated payment', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne(['category' => $category]);
    $payment = PaymentFactory::createOne([
        'subscription' => $subscription,
        'type' => PaymentType::Generated,
        'amount' => new Money(1599, Currency::USD),
    ]);

    $client->request(method: 'POST', uri: '/payments/' . $payment->id . '/validate');

    $this->assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $entityManager->clear();

    $updated = $entityManager->getRepository(Payment::class)->find($payment->id);
    expect($updated)->not->toBeNull();
    expect($updated->type)->toBe(PaymentType::Verified)
        ->and($updated->amount->minorAmount)->toBe(1599)
    ;
});

test('post request with invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'POST', uri: '/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/validate');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});
