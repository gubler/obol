<?php

// ABOUTME: Feature tests for ValidatePaymentController verifying one-click confirmation of generated payments.
// ABOUTME: Validating flips a Generated payment to Verified, leaving the amount unchanged.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Payment;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentType;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ValidatePaymentControllerTest extends WebTestCase
{
    public function testPostRequestValidatesAGeneratedPayment(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne(['category' => $category]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'type' => PaymentType::Generated,
            'amount' => new Money(1599, Currency::USD),
        ]);

        $client->request(method: 'POST', uri: '/payments/' . $payment->id . '/validate');

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $updated = $entityManager->getRepository(Payment::class)->find($payment->id);
        self::assertNotNull($updated);
        self::assertSame(PaymentType::Verified, $updated->type);
        self::assertSame(1599, $updated->amount->minorAmount);
    }

    public function testPostRequestWithInvalidIdReturns404(): void
    {
        $client = self::createClient();

        $client->request(method: 'POST', uri: '/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/validate');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }
}
