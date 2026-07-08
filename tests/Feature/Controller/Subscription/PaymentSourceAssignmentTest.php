<?php

// ABOUTME: Feature tests for assigning a payment source to a subscription through the create/edit forms.
// ABOUTME: Verifies the picker appears only when sources exist and that changes land in the audit trail.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Enum\SubscriptionEventType;
use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class PaymentSourceAssignmentTest extends AuthenticatedTestCase
{
    public function testCreateFormShowsThePaymentSourcePickerWhenSourcesExist(): void
    {
        $client = $this->authenticatedClient();

        PaymentSourceFactory::createOne(['name' => 'Amex 1234']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/subscriptions/new');

        self::assertResponseIsSuccessful();
        $select = $crawler->filter('select[name="create_subscription[paymentSource]"]');
        self::assertCount(1, $select);
        self::assertStringContainsString('Amex 1234', $select->text());
    }

    public function testCreateFormHidesThePaymentSourcePickerWhenNoneExist(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('select[name="create_subscription[paymentSource]"]'));
    }

    public function testEditingASubscriptionToAssignAPaymentSourceRecordsAnAuditEvent(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);
        $subscriptionId = $subscription->id;

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/subscriptions/' . $subscriptionId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_subscription[paymentSource]'] = (string) $source->id;
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/app/subscriptions/' . $subscriptionId);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $updated = $entityManager->getRepository(Subscription::class)->find($subscriptionId);

        self::assertNotNull($updated);
        self::assertNotNull($updated->paymentSource);
        self::assertSame('Amex 1234', $updated->paymentSource->name);

        // The change is recorded in the audit trail as an Update event carrying the paymentSource field.
        $hasPaymentSourceEvent = false;
        foreach ($updated->subscriptionEvents as $event) {
            if (SubscriptionEventType::Update === $event->type && \array_key_exists('paymentSource', $event->context)) {
                $hasPaymentSourceEvent = true;
            }
        }

        self::assertTrue($hasPaymentSourceEvent, 'Expected an Update event recording the paymentSource change.');
    }
}
