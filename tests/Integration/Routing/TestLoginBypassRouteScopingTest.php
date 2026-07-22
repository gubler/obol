<?php

// ABOUTME: Integration test that the /_test login-bypass route is registered only outside production.
// ABOUTME: In prod the route does not exist at all - a structural second line behind the controller guard.

declare(strict_types=1);

namespace App\Tests\Integration\Routing;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class TestLoginBypassRouteScopingTest extends KernelTestCase
{
    public function testTheTestLoginBypassRouteIsAbsentInProd(): void
    {
        $router = $this->routerForEnvironment('prod');

        self::assertNull(
            $router->getRouteCollection()->get('test_login_as'),
            'the /_test login-bypass route must not be registered in the prod environment',
        );
    }

    public function testTheTestLoginBypassRouteIsPresentInTest(): void
    {
        $router = $this->routerForEnvironment('test');

        self::assertNotNull(
            $router->getRouteCollection()->get('test_login_as'),
            'the /_test login-bypass route must stay available in the test environment for Panther tests',
        );
    }

    private function routerForEnvironment(string $environment): RouterInterface
    {
        // Boot the real kernel for the target environment. self::getContainer() is unavailable here: it
        // needs the test-only service container (framework.test), which the prod environment does not have.
        self::bootKernel(['environment' => $environment, 'debug' => false]);
        $router = self::$kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router;
    }
}
