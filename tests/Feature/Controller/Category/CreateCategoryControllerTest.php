<?php

// ABOUTME: Feature tests for CreateCategoryController verifying category creation functionality.
// ABOUTME: Tests ensure proper form rendering, validation, and successful creation with redirects.

declare(strict_types=1);

use App\Entity\Category;
use App\Factory\CategoryFactory;
use Doctrine\ORM\EntityManagerInterface;

test('get request displays create form', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/categories/new');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'New Category');
    $this->assertSelectorExists(selector: 'form');
    $this->assertSelectorExists(selector: 'input[name="create_category[name]"]');
    $this->assertSelectorExists(selector: 'button[type="submit"]');
});

test('shows cancel link back to index', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/categories/new');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/categories"]');
});

test('post request with valid data creates category', function (): void {
    $client = $this->createClient();

    $crawler = $client->request(method: 'GET', uri: '/categories/new');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_category[name]'] = 'New Test Category';

    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/categories');

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Category::class);

    $category = $repository->findOneBy(criteria: ['name' => 'New Test Category']);

    expect($category)->not->toBeNull();
    expect($category->name)->toBe('New Test Category');
});

test('post request with valid data shows success flash message', function (): void {
    $client = $this->createClient();

    $crawler = $client->request(method: 'GET', uri: '/categories/new');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_category[name]'] = 'Flash Test Category';

    $client->submit(form: $form);
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-success', text: 'Category created successfully');
});

test('post request with empty name shows validation error', function (): void {
    $client = $this->createClient();

    $crawler = $client->request(method: 'GET', uri: '/categories/new');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_category[name]'] = '';

    $client->submit(form: $form);

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.form-error');
    $this->assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
});

test('post request with too long name shows validation error', function (): void {
    $client = $this->createClient();

    $crawler = $client->request(method: 'GET', uri: '/categories/new');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_category[name]'] = str_repeat(string: 'a', times: 256);

    $client->submit(form: $form);

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.form-error');
    $this->assertSelectorTextContains(selector: 'body', text: 'This value is too long');
});

test('post request with only whitespace shows validation error', function (): void {
    $client = $this->createClient();

    $crawler = $client->request(method: 'GET', uri: '/categories/new');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_category[name]'] = '   ';

    $client->submit(form: $form);

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.form-error');
});

test('form includes csrf protection', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/categories/new');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'input[name="create_category[_token]"]');
});

test('post request does not create category when validation fails', function (): void {
    $client = $this->createClient();

    $crawler = $client->request(method: 'GET', uri: '/categories/new');

    $initialCount = CategoryFactory::count();

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_category[name]'] = '';

    $client->submit(form: $form);

    $finalCount = CategoryFactory::count();

    expect($finalCount)->toBe($initialCount);
});
