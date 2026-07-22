<?php

// ABOUTME: Feature tests for the "move all subscriptions" action on a payment source.
// ABOUTME: Covers the form's visibility, a successful bulk move with audit events, and a rejected target.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Entity\Subscription;
use App\Enum\SubscriptionEventType;
use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

final class ReassignPaymentSourceSubscriptionsControllerTest extends AuthenticatedTestCase
{
    public function testShowPageOffersTheMoveAllFormWhenThereAreSubscriptionsAndOtherSources(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        PaymentSourceFactory::createOne(['name' => 'Visa 5678']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);

        $crawler = $client->request(method: Request::METHOD_GET, uri: '/app/payment-sources/' . $source->id);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[action="/app/payment-sources/' . $source->id . '/reassign"]'));
        self::assertStringContainsString('Visa 5678', $crawler->filter('select[name="reassign_subscriptions[target]"]')->text());
    }

    public function testDoesNotOfferTheMoveAllFormWhenThereIsNoOtherSource(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);

        $crawler = $client->request(method: Request::METHOD_GET, uri: '/app/payment-sources/' . $source->id);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action="/app/payment-sources/' . $source->id . '/reassign"]'));
    }

    public function testMovesEverySubscriptionToTheTargetAndRecordsAudit(): void
    {
        $client = $this->authenticatedClient();

        $from = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        $to = PaymentSourceFactory::createOne(['name' => 'Visa 5678']);
        SubscriptionFactory::createOne(['paymentSource' => $from, 'name' => 'Netflix']);
        SubscriptionFactory::createOne(['paymentSource' => $from, 'name' => 'Spotify']);
        $toId = $to->id;

        $crawler = $client->request(method: Request::METHOD_GET, uri: '/app/payment-sources/' . $from->id);
        $form = $crawler->filter('form[action="/app/payment-sources/' . $from->id . '/reassign"]')->form();
        $form['reassign_subscriptions[target]'] = (string) $to->id;
        $client->submit($form);

        self::assertResponseRedirects(expectedLocation: '/app/payment-sources/' . $from->id);
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

    public function testRejectsATargetThatIsNotOneOfTheUsersOtherSources(): void
    {
        $client = $this->authenticatedClient();

        $from = PaymentSourceFactory::createOne(['name' => 'Amex 1234']);
        PaymentSourceFactory::createOne(['name' => 'Visa 5678']);
        SubscriptionFactory::createOne(['paymentSource' => $from, 'name' => 'Netflix']);

        // Start from the real rendered form (valid CSRF token, same-origin) and forge the target to an id
        // that is not one of the owner's other sources. The owner-scoped choice list rejects it as an
        // invalid choice, so nothing moves and the page re-flashes an error.
        $crawler = $client->request(method: Request::METHOD_GET, uri: '/app/payment-sources/' . $from->id);
        $form = $crawler->filter('form[action="/app/payment-sources/' . $from->id . '/reassign"]')->form();
        $values = $form->getPhpValues();
        $values['reassign_subscriptions']['target'] = (string) new Ulid();
        $client->request(method: Request::METHOD_POST, uri: $form->getUri(), parameters: $values);

        self::assertResponseRedirects(expectedLocation: '/app/payment-sources/' . $from->id);
        $client->followRedirect();
        self::assertSelectorTextContains(selector: '.flash-error', text: 'Could not move the subscriptions');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $netflix = $entityManager->getRepository(Subscription::class)->findOneBy(['name' => 'Netflix']);
        self::assertNotNull($netflix->paymentSource);
        self::assertTrue($netflix->paymentSource->id->equals($from->id), 'the subscription must not have moved');
    }
}
