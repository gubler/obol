<?php

// ABOUTME: Feature tests for CreateCategoryController verifying category creation functionality.
// ABOUTME: Tests ensure proper form rendering, validation, and successful creation with redirects.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreateCategoryControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysCreateForm(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'New Category');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'input[name="create_category[name]"]');
        self::assertSelectorExists(selector: 'button[type="submit"]');
    }

    public function testShowsCancelLinkBackToIndex(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/categories"]');
    }

    public function testPostRequestWithValidDataCreatesCategory(): void
    {
        $client = static::createClient();

        $crawler = $client->request(method: 'GET', uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = 'New Test Category';

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/categories');

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $category = $repository->findOneBy(criteria: ['name' => 'New Test Category']);

        self::assertNotNull($category);
        self::assertSame('New Test Category', $category->name);
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();

        $crawler = $client->request(method: 'GET', uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = 'Flash Test Category';

        $client->submit(form: $form);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Category created successfully');
    }

    public function testPostRequestWithEmptyNameShowsValidationError(): void
    {
        $client = static::createClient();

        $crawler = $client->request(method: 'GET', uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithTooLongNameShowsValidationError(): void
    {
        $client = static::createClient();

        $crawler = $client->request(method: 'GET', uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = str_repeat(string: 'a', times: 256);

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value is too long');
    }

    public function testPostRequestWithOnlyWhitespaceShowsValidationError(): void
    {
        $client = static::createClient();

        $crawler = $client->request(method: 'GET', uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = '   ';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="create_category[_token]"]');
    }

    public function testPostRequestDoesNotCreateCategoryWhenValidationFails(): void
    {
        $client = static::createClient();

        $crawler = $client->request(method: 'GET', uri: '/categories/new');

        $initialCount = CategoryFactory::count();

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = '';

        $client->submit(form: $form);

        $finalCount = CategoryFactory::count();

        self::assertSame($initialCount, $finalCount);
    }
}
