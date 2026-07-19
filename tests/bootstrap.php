<?php

// ABOUTME: PHPUnit bootstrap — boots the test kernel and rebuilds the test
// ABOUTME: PostgreSQL database from migrations before the suite runs.

declare(strict_types=1);

use App\Kernel;
use App\Tests\Support\TestDatabase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Panther\PantherTestCase;

require dirname(path: __DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(path: dirname(path: __DIR__) . '/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(mask: 0000);
}

/*
 * Pin Panther's spawned PHP CLI server to `localhost` rather than the 127.0.0.1 default. Chromium
 * rejects IP addresses as WebAuthn RP IDs (startRegistration throws SecurityError "127.0.0.1 is an
 * invalid domain"), which PasskeyFlowTest hits directly. Setting this once means every Panther client
 * uses localhost:9080, so WEBAUTHN_RP_ID stays `localhost` and only WEBAUTHN_ALLOWED_ORIGINS needs the
 * spawned-server URL. Reflection because PantherTestCase::$defaultOptions is protected.
 */
(static function (): void {
    $property = new ReflectionProperty(PantherTestCase::class, 'defaultOptions');
    $options = $property->getValue();
    $options['hostname'] = 'localhost';
    $property->setValue(null, $options);
})();

// Create and boot 'test' kernel
$kernel = new Kernel(environment: 'test', debug: true);
$kernel->boot();

// Create new application
$application = new Application(kernel: $kernel);
$application->setAutoExit(boolean: false);

// Load the fixtures into the DB
// This is here in case we want it in the future, but it isn't used
// in favor of only create the data we need for each test via Factories
$loadFixturesCommand = static function () use ($application): void {
    $input = new ArrayInput(parameters: [
        'command' => 'doctrine:fixtures:load',
        '--group' => ['default'],
        '--no-interaction' => true,
    ]);

    $input->setInteractive(interactive: false);

    $application->run(input: $input, output: new ConsoleOutput());
};

// Drop, create, migrate - throws rather than let the suite run against a database it could not rebuild.
new TestDatabase($application, new ConsoleOutput())->rebuild();
