<?php

// ABOUTME: Feature tests for the "move all subscriptions" action on a payment source.
// ABOUTME: Covers the form's visibility, a successful bulk move with audit events, and a bad target.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Entity\Subscription;
use App\Enum\SubscriptionEventType;
use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ReassignPaymentSourceSubscriptionsControllerTest extends AuthenticatedTestCase
{
    public function testShowPageOffersTheMoveAllFormWhenThereAreSubscriptionsAndOtherSources(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        PaymentSourceFactory::createOne(['name' => 'Visa 5678']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources/' . $source->id);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[action="/payment-sources/' . $source->id . '/reassign"]'));
        self::assertStringContainsString('Visa 5678', $crawler->filter('select[name="target"]')->text());
    }

    public function testDoesNotOfferTheMoveAllFormWhenThereIsNoOtherSource(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources/' . $source->id);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('select[name="target"]'));
    }

    public function testMovesEverySubscriptionToTheTargetAndRecordsAudit(): void
    {
        $client = $this->authenticatedClient();

        $from = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        $to = PaymentSourceFactory::createOne(['name' => 'Visa 5678']);
        SubscriptionFactory::createOne(['paymentSource' => $from, 'name' => 'Netflix']);
        SubscriptionFactory::createOne(['paymentSource' => $from, 'name' => 'Spotify']);
        $toId = $to->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/payment-sources/' . $from->id . '/reassign', parameters: ['target' => (string) $to->id]);

        self::assertResponseRedirects(expectedLocation: '/payment-sources/' . $from->id);
        $client->followRedirect();
        self::assertSelectorTextContains(selector: '.flash-success', text: 'Subscriptions moved successfully');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $subscriptions = $entityManager->getRepository(Subscription::class)->findBy(['name' => 'Netflix']);
        $netflix = $subscriptions[0];
        self::assertNotNull($netflix->paymentSource);
        self::assertTrue($netflix->paymentSource->id->equals($toId));

        $hasEvent = false;
        foreach ($netflix->subscriptionEvents as $event) {
            if (SubscriptionEventType::Update === $event->type && \array_key_exists('paymentSource', $event->context)) {
                $hasEvent = true;
            }
        }

        self::assertTrue($hasEvent, 'Expected a paymentSource Update event on the moved subscription.');
    }

    public function testRejectsAnInvalidTarget(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/payment-sources/' . $source->id . '/reassign', parameters: ['target' => 'not-a-ulid']);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-error', text: 'Could not move the subscriptions');
    }
}
