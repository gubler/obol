<?php

// ABOUTME: Feature tests for EditPaymentController verifying payment amendment via the edit form.
// ABOUTME: Covers prefilled form rendering, amending (which verifies the payment), and 404 handling.

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

final class EditPaymentControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysTheEditFormPrefilledWithThePaymentAmount(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(1599, Currency::USD)]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'type' => PaymentType::Generated,
            'amount' => new Money(1599, Currency::USD),
            'paidDate' => new \DateTimeImmutable('2024-01-01'),
        ]);

        $client->request(method: 'GET', uri: '/payments/' . $payment->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="amend_payment[amount]"][value="1599"]');
    }

    public function testPostRequestAmendsThePaymentAndVerifiesIt(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(1599, Currency::USD)]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'type' => PaymentType::Generated,
            'amount' => new Money(1599, Currency::USD),
            'paidDate' => new \DateTimeImmutable('2024-01-01'),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/payments/' . $payment->id . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form([
            'amend_payment[amount]' => '1299',
            'amend_payment[paidDate]' => '2024-01-05',
        ]);
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $updated = $entityManager->getRepository(Payment::class)->find($payment->id);
        self::assertNotNull($updated);
        self::assertSame(1299, $updated->amount->minorAmount);
        self::assertSame(PaymentType::Verified, $updated->type);
    }

    public function testGetRequestWithInvalidIdReturns404(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }
}
