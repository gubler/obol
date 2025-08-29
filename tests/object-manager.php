<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

new Dotenv()->bootEnv(__DIR__ . '/../.env');

$appEnv = $_SERVER['APP_ENV'];
if (!is_string($appEnv)) {
    throw new DomainException(message: 'APP_ENV must be a string');
}

$kernel = new Kernel(environment: $appEnv, debug: (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
