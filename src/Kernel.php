<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Called by Symfony's MicroKernelTrait at boot to validate the APP_ENV
     * value (Symfony 8.1+; added by the framework-bundle recipe). PHPStan
     * doesn't see the framework-side reflection call.
     *
     * @return list<string> An array of allowed values for APP_ENV
     *
     * @phpstan-ignore method.unused
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
