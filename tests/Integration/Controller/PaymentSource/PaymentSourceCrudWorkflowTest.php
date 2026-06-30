<?php

// ABOUTME: Integration tests for complete PaymentSource CRUD workflows end-to-end.
// ABOUTME: Tests verify create -> edit -> delete sequences and alphabetical list order with real data.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\PaymentSource;

use App\Entity\PaymentSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class PaymentSourceCrudWorkflowTest extends WebTestCase
{
    public function testCompleteCreateEditDeleteWorkflow(): void
    {
        $client = self::createClient();

        // Create
        $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Workflow Source';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/payment-sources');
        $client->followRedirect();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: PaymentSource::class);

        $source = $repository->findOneBy(criteria: ['name' => 'Workflow Source']);
        self::assertNotNull($source);
        $sourceId = $source->id;

        // Edit
        $crawler = $client->request(method: 'GET', uri: '/payment-sources/' . $sourceId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Updated Source';
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/payment-sources/' . $sourceId);
        $client->followRedirect();

        $entityManager->clear();
        $updated = $repository->find($sourceId);
        self::assertNotNull($updated);
        self::assertSame('Updated Source', $updated->name);

        // Delete
        $client->request(method: 'POST', uri: '/payment-sources/' . $sourceId . '/delete');
        self::assertResponseRedirects(expectedLocation: '/payment-sources');

        $entityManager->clear();
        self::assertNull($repository->find($sourceId));
    }

    public function testCreateMultiplePaymentSourcesAndVerifyListOrder(): void
    {
        $client = self::createClient();

        foreach (['Zebra Card', 'Alpha Card', 'Beta Card'] as $name) {
            $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');
            $form = $crawler->selectButton(value: 'Save')->form();
            $form['payment_source[name]'] = $name;
            $client->submit(form: $form);
            $client->followRedirect();
        }

        $crawler = $client->request(method: 'GET', uri: '/payment-sources');

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
