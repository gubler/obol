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
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\SameOriginPostTrait;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

final class ValidatePaymentControllerTest extends AuthenticatedTestCase
{
    use SameOriginPostTrait;

    public function testPostRequestValidatesAGeneratedPayment(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne(['category' => $category]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'type' => PaymentType::Generated,
            'amount' => new Money(1599, Currency::USD),
        ]);

        $this->postSameOrigin($client, '/app/payments/' . $payment->id . '/validate');

        self::assertResponseRedirects(expectedLocation: '/app/subscriptions/' . $subscription->id);

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
        $client = $this->authenticatedClient();

        $this->postSameOrigin($client, '/app/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/validate');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }
}
