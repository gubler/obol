<?php

// ABOUTME: Panther E2E for the passkey register + assertion ceremonies against a real browser.
// ABOUTME: Uses Chromium's W3C virtual authenticator, so no real Touch ID / YubiKey is needed.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Auth;

use App\Factory\UserFactory;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Panther runs the app in a separate PHP CLI server process, so DAMA's per-test transaction rollback
 * cannot reach it. We opt out of the rollback and truncate by hand; the seeded founder is committed in
 * this process and so is visible to the browser's server (both run APP_ENV=test against app_test).
 *
 * Chromium exposes the W3C WebAuthn extension via Selenium WebDriver at
 *   POST /session/{sessionId}/webauthn/authenticator
 * (https://www.w3.org/TR/webauthn-3/#sctn-automation). A virtual authenticator added there auto-consents
 * on every ceremony, so startRegistration / startAuthentication complete without any UI interaction.
 *
 * The RP id is `localhost`; tests/bootstrap.php pins Panther's server to localhost:9080 so the ceremony
 * origin (http://localhost:9080, in WEBAUTHN_ALLOWED_ORIGINS) has `localhost` as a valid RP-id suffix.
 */
#[SkipDatabaseRollback]
final class PasskeyFlowTest extends PantherTestCase
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
            ->executeStatement('TRUNCATE passkey_credential, user_email, "user" RESTART IDENTITY CASCADE')
        ;
    }

    #[WithoutErrorHandler]
    public function testRegisterThenSignInViaPasskey(): void
    {
        $founder = UserFactory::founder();

        $client = self::createPantherClient([], [], self::pantherClientOptions());
        $authenticatorId = self::addVirtualAuthenticator($client);
        self::assertNotSame('', $authenticatorId, 'Virtual authenticator should be installed.');

        // 1. Log in via the non-prod bypass (Panther can't drive the magic-link email loop), then open
        //    the register page.
        $client->request('GET', '/_test/login-as/' . $founder->email);
        $client->request('GET', '/app/account/passkeys/new');

        // Wait until the Stimulus connect() callback reveals the register button - the JS-readiness signal.
        $client->wait(5)->until(static function () use ($client): bool {
            $class = (string) $client->executeScript(
                'const b = document.querySelector(\'[data-test="passkey-register-submit"]\'); return b ? b.className : "";',
            );

            return '' !== $class && !str_contains($class, 'hidden');
        });

        // 2. Register. The virtual authenticator auto-consents; the controller redirects to the Access
        //    section, where the passkey list lives.
        $client->executeScript('document.querySelector(\'[data-test="passkey-register-submit"]\').click();');
        try {
            $client->wait(10)->until(static fn (): bool => str_contains($client->getCurrentURL(), '/app/account/access'));
        } catch (TimeoutException $timeoutException) {
            self::fail(self::diagnosticFailure($client, 'registration redirect timed out', $timeoutException));
        }

        $rowCount = (int) $client->executeScript(
            'return document.querySelectorAll(\'[data-test="passkey-row"]\').length;',
        );
        self::assertSame(1, $rowCount, 'Exactly one passkey row should be present after registration.');

        // 3. Log out, then hit /login. Conditional UI may silently assert on connect(); otherwise we
        //    click the explicit button. Either path ends with the success handler's JSON {redirect}
        //    navigating the browser away from /login.
        $client->request('GET', '/logout');
        $client->request('GET', '/login');

        try {
            $client->wait(10)->until(static function () use ($client): bool {
                if (!str_contains($client->getCurrentURL(), '/login')) {
                    return true;
                }

                $class = (string) $client->executeScript(
                    'const b = document.querySelector(\'[data-test="passkey-login-submit"]\'); return b ? b.className : "";',
                );
                if ('' === $class || str_contains($class, 'hidden')) {
                    return false;
                }

                $client->executeScript('document.querySelector(\'[data-test="passkey-login-submit"]\').click();');

                return false;
            });
        } catch (TimeoutException $timeoutException) {
            self::fail(self::diagnosticFailure($client, 'assertion redirect timed out', $timeoutException));
        }

        self::assertStringNotContainsString(
            '/login',
            $client->getCurrentURL(),
            'After passkey assertion the browser should leave /login.',
        );
    }

    /**
     * Add a virtual CTAP2 authenticator with resident-key + user-verification support, auto-consenting
     * on every ceremony. Returns the authenticator id Chromium assigns (some drivers wrap it in an
     * array, others return the bare string).
     *
     * @see https://chromedevtools.github.io/devtools-protocol/tot/WebAuthn
     */
    private static function addVirtualAuthenticator(Client $client): string
    {
        /** @var RemoteWebDriver $driver */
        $driver = $client->getWebDriver();

        $response = $driver->executeCustomCommand(
            '/session/:sessionId/webauthn/authenticator',
            'POST',
            [
                'protocol' => 'ctap2',
                'transport' => 'internal',
                'hasResidentKey' => true,
                'hasUserVerification' => true,
                'isUserConsenting' => true,
                'isUserVerified' => true,
            ],
        );

        if (\is_string($response)) {
            return $response;
        }

        if (\is_array($response)) {
            return (string) ($response['authenticatorId'] ?? '');
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function pantherClientOptions(): array
    {
        return [
            'browser' => PantherTestCase::CHROME,
            'arguments' => ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'],
            'capabilities' => ['acceptInsecureCerts' => true],
        ];
    }

    /**
     * Build a self-contained failure message surfacing the page state Panther sees when a wait times
     * out - current URL, the passkey status text the Stimulus controllers expose, and which controllers
     * mounted - so a CI failure is diagnosable without screenshot artefacts.
     */
    private static function diagnosticFailure(Client $client, string $where, \Throwable $original): string
    {
        $snapshot = $client->executeScript(<<<'JS'
            return {
                url: window.location.href,
                title: document.title,
                registerStatus: (() => {
                    const s = document.querySelector('[data-passkey-register-target="status"]');
                    return s ? s.textContent.trim() : null;
                })(),
                loginStatus: (() => {
                    const s = document.querySelector('[data-passkey-login-target="status"]');
                    return s ? s.textContent.trim() : null;
                })(),
                controllers: Array.from(document.querySelectorAll('[data-controller]')).map(e => e.getAttribute('data-controller')),
            };
            JS);

        return \sprintf(
            "%s: %s\n%s",
            $where,
            $original->getMessage() ?: '(empty TimeoutException)',
            json_encode($snapshot, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) ?: '<no snapshot>',
        );
    }
}
