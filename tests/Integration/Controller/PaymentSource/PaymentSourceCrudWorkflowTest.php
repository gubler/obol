<?php

// ABOUTME: Integration tests for complete PaymentSource CRUD workflows end-to-end.
// ABOUTME: Tests verify create -> edit -> delete sequences and alphabetical list order with real data.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\PaymentSource;

use App\Entity\PaymentSource;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\SameOriginPostTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

final class PaymentSourceCrudWorkflowTest extends AuthenticatedTestCase
{
    use SameOriginPostTrait;

    public function testCompleteCreateEditDeleteWorkflow(): void
    {
        $client = $this->authenticatedClient();

        // Create
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/payment-sources/new');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Workflow Source';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/app/payment-sources');
        $client->followRedirect();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: PaymentSource::class);

        $source = $repository->findOneBy(criteria: ['name' => 'Workflow Source']);
        self::assertNotNull($source);
        $sourceId = $source->id;

        // Edit
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/payment-sources/' . $sourceId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Updated Source';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/app/payment-sources/' . $sourceId);
        $client->followRedirect();

        $entityManager->clear();
        $updated = $repository->find($sourceId);
        self::assertNotNull($updated);
        self::assertSame('Updated Source', $updated->name);

        // Delete
        $this->postSameOrigin($client, '/app/payment-sources/' . $sourceId . '/delete');
        self::assertResponseRedirects(expectedLocation: '/app/payment-sources');

        $entityManager->clear();
        self::assertNull($repository->find($sourceId));
    }

    public function testCreateMultiplePaymentSourcesAndVerifyListOrder(): void
    {
        $client = $this->authenticatedClient();

        foreach (['Zebra Card', 'Alpha Card', 'Beta Card'] as $name) {
            $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/payment-sources/new');
            $form = $crawler->selectButton(value: 'Save')->form();
            $form['payment_source[name]'] = $name;
            $client->submit(form: $form);
            $client->followRedirect();
        }

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/payment-sources');

        $names = $crawler->filter('table tbody tr td:first-child')->each(
            fn (Crawler $node): string => trim($node->text())
        );

        $alphaIndex = array_search('Alpha Card', $names, true);
        $betaIndex = array_search('Beta Card', $names, true);
        $zebraIndex = array_search('Zebra Card', $names, true);

        self::assertNotFalse($alphaIndex);
        self::assertNotFalse($betaIndex);
        self::assertNotFalse($zebraIndex);
        self::assertLessThan($betaIndex, $alphaIndex);
        self::assertLessThan($zebraIndex, $betaIndex);
    }
}
