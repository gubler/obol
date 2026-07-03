<?php

// ABOUTME: Panther browser test for the new-subscription color-sync controller (#235), the repo's
// ABOUTME: first real-browser E2E - proves the Stimulus controller loads, syncs, and tears down live.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Subscription;

use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Factory\UserFactory;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Panther runs the app in a separate PHP CLI server process, so DAMA's per-test transaction
 * rollback cannot reach it. We opt out of the rollback and truncate by hand instead; the seeded
 * categories are committed in this process and so are visible to the browser's server.
 */
#[SkipDatabaseRollback]
final class ColorSyncBrowserTest extends PantherTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::truncateTables();
    }

    protected function tearDown(): void
    {
        self::truncateTables();
        parent::tearDown();
    }

    private static function truncateTables(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->executeStatement('TRUNCATE subscription_event, payment, subscription, category, user_email, "user" RESTART IDENTITY CASCADE')
        ;
    }

    #[WithoutErrorHandler]
    public function testTheSwatchSyncsToTheCategoryColorUntilTheUserPicksOne(): void
    {
        $founder = UserFactory::founder();
        $apple = CategoryFactory::createOne(['name' => 'Apple', 'color' => TileColor::Teal]);
        $spotify = CategoryFactory::createOne(['name' => 'Spotify', 'color' => TileColor::Blue]);

        $client = self::createPantherClient([], [], [
            'browser' => PantherTestCase::CHROME,
            'arguments' => ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'],
            'capabilities' => ['acceptInsecureCerts' => true],
        ]);

        // The app is authenticated-by-default; log the browser in via the non-prod bypass before
        // reaching the protected form.
        $client->request('GET', '/_test/login-as/' . $founder->email);
        $client->request('GET', '/subscriptions/new');
        $client->waitForVisibility('select[name="create_subscription[category]"]', 10);

        $selectCategory = static function (string $id) use ($client): void {
            $select = new WebDriverSelect($client->findElement(WebDriverBy::name('create_subscription[category]')));
            $select->selectByValue($id);
        };

        // Choosing a category checks that category's color swatch.
        $selectCategory($apple->id->toBase32());
        $client->waitFor('input[name="create_subscription[color]"][value="teal"]:checked', 5);

        // Changing the category re-syncs the color.
        $selectCategory($spotify->id->toBase32());
        $client->waitFor('input[name="create_subscription[color]"][value="blue"]:checked', 5);

        // The user picks a swatch themselves (clicking its label, as the radios are visually hidden).
        $redId = $client->findElement(WebDriverBy::cssSelector('input[name="create_subscription[color]"][value="red"]'))
            ->getAttribute('id')
        ;
        $client->findElement(WebDriverBy::cssSelector('label[for="' . $redId . '"]'))->click();
        $client->waitFor('input[name="create_subscription[color]"][value="red"]:checked', 5);

        // After the user has taken control, a category change no longer moves the color.
        $selectCategory($apple->id->toBase32());
        $stillRed = $client->findElement(
            WebDriverBy::cssSelector('input[name="create_subscription[color]"][value="red"]'),
        );
        self::assertTrue($stillRed->isSelected(), 'the user-picked color must survive a later category change');
        $teal = $client->findElement(
            WebDriverBy::cssSelector('input[name="create_subscription[color]"][value="teal"]'),
        );
        self::assertFalse($teal->isSelected(), 'a detached controller must not re-sync the color');
    }
}
