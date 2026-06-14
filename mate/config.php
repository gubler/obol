<?php

declare(strict_types=1);

// User's service configuration file
// This file is loaded into the Symfony DI container

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // Override default parameters here
        // ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
        // ->set('mate.env_file', ['.env']) // This will load mate/.env and mate/.env.local

        // Run PHPStan with a raised memory limit. PHPStan analysing this
        // Symfony/Doctrine app exhausts the container's 128M CLI memory_limit and
        // gets killed mid-run (this is why `mise run sa` passes --memory-limit=4G).
        // The phpstan-extension uses `custom_command` as a command prefix
        // ([...prefix, 'analyse', ...args]), so we prepend `php -d memory_limit=4G`
        // ahead of the binary. Mate runs inside the php container, so this invokes
        // the in-container php/phpstan directly.
        ->set('matesofmate_phpstan.custom_command', ['php', '-d', 'memory_limit=4G', 'vendor/bin/phpstan'])
    ;

    $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()

        // Register your custom services here
    ;
};
