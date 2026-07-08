<?php

// ABOUTME: Feature tests for DeletePaymentSourceController verifying deletion and the in-use guard.
// ABOUTME: Covers empty-source deletion, the blocked-while-in-use path, 404, and method restriction.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Entity\PaymentSource;
use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class DeletePaymentSourceControllerTest extends AuthenticatedTestCase
{
    public function testDeletesPaymentSourceWithoutSubscriptionsSuccessfully(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Empty Source']);
        $sourceId = $source->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/payment-sources/' . $sourceId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/app/payment-sources');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(PaymentSource::class)->find($sourceId));
    }

    public function testDeleteSuccessShowsFlashMessage(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Test Source']);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/payment-sources/' . $source->id . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Payment source deleted successfully');
    }

    public function testCannotDeletePaymentSourceWithSubscriptions(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'In-Use Source']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);
        $sourceId = $source->id;

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/payment-sources/' . $sourceId . '/delete');

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        self::assertNotNull($entityManager->getRepository(PaymentSource::class)->find($sourceId));
    }

    public function testDeleteFailureShowsErrorFlashMessage(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'In-Use Source']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Spotify']);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/payment-sources/' . $source->id . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-error', text: 'Cannot delete payment source with subscriptions');
    }

    public function testReturns404ForNonExistentPaymentSource(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/payment-sources/' . new Ulid() . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = $this->authenticatedClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Test Source']);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/payment-sources/' . $source->id . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 405);
    }
}
