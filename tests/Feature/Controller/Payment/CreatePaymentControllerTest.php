<?php

// ABOUTME: Feature tests for CreatePaymentController verifying payment creation via form.
// ABOUTME: Tests form display, valid submission, validation errors, and 404 for invalid subscription.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Payment;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\TranslationAssertions;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreatePaymentControllerTest extends WebTestCase
{
    use TranslationAssertions;

    public function testDisplaysCreatePaymentForm(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        // The new-payment page is not crawled by the i18n tripwire, so guard it here (ADR-0012).
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'new payment page');
    }

    public function testCreatesPaymentWithValidData(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $initialPaymentCount = \count($subscription->payments);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '15.99',
            'create_payment[paidDate]' => '2025-01-15',
        ]);

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Subscription::class);
        $entityManager->clear();

        $updatedSubscription = $repository->find($subscription->id);
        self::assertNotNull($updatedSubscription);
        self::assertGreaterThan($initialPaymentCount, \count($updatedSubscription->payments));
    }

    public function testShowsSuccessFlashMessageAfterCreation(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '15.99',
            'create_payment[paidDate]' => '2025-01-15',
        ]);
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Payment recorded successfully');
    }

    public function testShowsValidationErrorsForInvalidData(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '',
            'create_payment[paidDate]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.text-danger');
    }

    public function testDoesNotOfferTheRestartControlForAnAutomatedSubscription(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#create_payment_restartPaymentGeneration');
    }

    public function testOffersTheRestartControlForAManualSubscription(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::new()->manual()->create(['name' => 'Netflix']);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#create_payment_restartPaymentGeneration');
    }

    public function testResumesAutomatedGenerationWhenRestartIsRequestedFromThePaymentForm(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::new()->manual()->create([
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);

        $future = (new \DateTimeImmutable('+40 days'))->format('Y-m-d');

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '15.99',
            'create_payment[paidDate]' => '2025-01-15',
            'create_payment[restartPaymentGeneration]' => '1',
            'create_payment[nextRenewal]' => $future,
        ]);

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();

        $updated = $entityManager->getRepository(Subscription::class)->find($subscription->id);
        self::assertNotNull($updated);
        self::assertTrue($updated->generatesPaymentsAutomatically());
        self::assertSame($future, $updated->nextRenewal->format('Y-m-d'));
    }

    public function testKeepsManualGenerationWhenThePaymentFormIsSubmittedWithoutRestart(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::new()->manual()->create([
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '15.99',
            'create_payment[paidDate]' => '2025-01-15',
        ]);

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();

        $updated = $entityManager->getRepository(Subscription::class)->find($subscription->id);
        self::assertFalse($updated->generatesPaymentsAutomatically());
    }

    public function testReturns404ForInvalidSubscriptionId(): void
    {
        $client = self::createClient();

        $client->request('GET', '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/payments/new');

        self::assertResponseStatusCodeSame(404);
    }
}
