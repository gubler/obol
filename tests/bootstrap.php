<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(path: __DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(path: dirname(path: __DIR__) . '/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(mask: 0000);
}

// Create and boot 'test' kernel
$kernel = new Kernel(environment: 'test', debug: true);
$kernel->boot();

// Create new application
$application = new Application(kernel: $kernel);
$application->setAutoExit(boolean: false);
/** @var Doctrine\Persistence\ManagerRegistry $doctrine */
$doctrine = $application
    ->getKernel()
    ->getContainer()
    ->get(id: 'doctrine')
;
/** @var Doctrine\DBAL\Connection $connection */
$connection = $doctrine->getConnection();
$dbPath = $connection->getParams()['path'];

// Unlink (delete) the DB file
// We can't use doctrine:database:drop because that doesn't work with SQLite
$dropDatabaseDoctrineCommand = static function () use ($dbPath): void {
    if (file_exists(filename: $dbPath)) {
        unlink(filename: $dbPath);
    }
};

// Touch (create) the DB file
// We can't use doctrine:database:create because that doesn't work with SQLite
$createDatabaseDoctrineCommand = static function () use ($dbPath): void {
    if (!file_exists(filename: $dbPath)) {
        touch(filename: $dbPath);
    }
};

// Migrate the database to the latest version
$migrateDoctrineCommand = static function () use ($application): void {
    $input = new ArrayInput(parameters: [
        'command' => 'doctrine:migrations:migrate',
        '--no-interaction' => true,
    ]);

    $input->setInteractive(interactive: false);

    $application->run(input: $input, output: new ConsoleOutput());
};

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

array_map(
    callback: '\call_user_func',
    array: [
        $dropDatabaseDoctrineCommand,
        $createDatabaseDoctrineCommand,
        $migrateDoctrineCommand,
        // $loadFixturesCommand,
    ]
);
