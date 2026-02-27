<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(path: __DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(path: dirname(path: __DIR__) . '/.env');

$env = $_SERVER['APP_ENV'];

Assert\Assertion::inArray($env, ['dev', 'test', 'prod']);
/** @var string $env */
$kernel = new Kernel($env, (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get(id: 'doctrine')->getManager();
