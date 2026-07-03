<?php

// ABOUTME: Feature tests for CreateCategoryController verifying category creation functionality.
// ABOUTME: Tests ensure proper form rendering, validation, and successful creation with redirects.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Entity\Category;
use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateCategoryControllerTest extends AuthenticatedTestCase
{
    public function testGetRequestDisplaysCreateForm(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'New Category');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'input[name="create_category[name]"]');
        self::assertSelectorExists(selector: 'button[type="submit"]');
    }

    public function testRendersAColorSwatchPickerAndAnIconPicker(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        self::assertResponseIsSuccessful();
        // The swatch picker mirrors the subscription form: one radio per TileColor, one pre-selected.
        self::assertCount(18, $crawler->filter('input[type="radio"][name="create_category[color]"]'));
        self::assertCount(1, $crawler->filter('input[type="radio"][name="create_category[color]"]:checked'));
        // The icon picker: one radio per CategoryIcon, each rendering its Lucide SVG, one pre-selected.
        $icons = $crawler->filter('input[type="radio"][name="create_category[icon]"]');
        self::assertGreaterThanOrEqual(30, $icons->count());
        self::assertCount(1, $crawler->filter('input[type="radio"][name="create_category[icon]"]:checked'));
        self::assertGreaterThanOrEqual(30, $crawler->filter('.icon-picker svg')->count());
    }

    public function testPersistsTheChosenColorAndIcon(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = 'Streaming';
        $form['create_category[color]'] = 'violet';
        $form['create_category[icon]'] = 'tv';

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/categories');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy(['name' => 'Streaming']);

        self::assertNotNull($category);
        self::assertSame(TileColor::Violet, $category->color);
        self::assertSame(CategoryIcon::Tv, $category->icon);
    }

    public function testShowsCancelLinkBackToIndex(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/categories"]');
    }

    public function testPostRequestWithValidDataCreatesCategory(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = 'New Test Category';

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/categories');

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $category = $repository->findOneBy(criteria: ['name' => 'New Test Category']);

        self::assertNotNull($category);
        self::assertSame('New Test Category', $category->name);
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = 'Flash Test Category';

        $client->submit(form: $form);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Category created successfully');
    }

    public function testPostRequestWithEmptyNameShowsValidationError(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithTooLongNameShowsValidationError(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = str_repeat(string: 'a', times: 256);

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value is too long');
    }

    public function testPostRequestWithOnlyWhitespaceShowsValidationError(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = '   ';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="create_category[_token]"]');
    }

    public function testPostRequestDoesNotCreateCategoryWhenValidationFails(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/categories/new');

        $initialCount = CategoryFactory::count();

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_category[name]'] = '';

        $client->submit(form: $form);

        $finalCount = CategoryFactory::count();

        self::assertSame($initialCount, $finalCount);
    }
}
