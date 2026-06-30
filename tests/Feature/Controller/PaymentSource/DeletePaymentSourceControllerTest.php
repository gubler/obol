<?php

// ABOUTME: Feature tests for DeletePaymentSourceController verifying deletion and the in-use guard.
// ABOUTME: Covers empty-source deletion, the blocked-while-in-use path, 404, and method restriction.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Entity\PaymentSource;
use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class DeletePaymentSourceControllerTest extends WebTestCase
{
    public function testDeletesPaymentSourceWithoutSubscriptionsSuccessfully(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Empty Source']);
        $sourceId = $source->id;

        $client->request(method: 'POST', uri: '/payment-sources/' . $sourceId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/payment-sources');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(PaymentSource::class)->find($sourceId));
    }

    public function testDeleteSuccessShowsFlashMessage(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Test Source']);

        $client->request(method: 'POST', uri: '/payment-sources/' . $source->id . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Payment source deleted successfully');
    }

    public function testCannotDeletePaymentSourceWithSubscriptions(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'In-Use Source']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Netflix']);
        $sourceId = $source->id;

        $client->request(method: 'POST', uri: '/payment-sources/' . $sourceId . '/delete');

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        self::assertNotNull($entityManager->getRepository(PaymentSource::class)->find($sourceId));
    }

    public function testDeleteFailureShowsErrorFlashMessage(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'In-Use Source']);
        SubscriptionFactory::createOne(['paymentSource' => $source, 'name' => 'Spotify']);

        $client->request(method: 'POST', uri: '/payment-sources/' . $source->id . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-error', text: 'Cannot delete payment source with subscriptions');
    }

    public function testReturns404ForNonExistentPaymentSource(): void
    {
        $client = self::createClient();

        $client->request(method: 'POST', uri: '/payment-sources/' . new Ulid() . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Test Source']);

        $client->request(method: 'GET', uri: '/payment-sources/' . $source->id . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 405);
    }
}
