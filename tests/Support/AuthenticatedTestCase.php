<?php

// ABOUTME: Base test case for feature tests that run behind the authenticated-by-default firewall.
// ABOUTME: authenticatedClient() logs in the founder by default; use static::createClient() for anonymous paths.

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AuthenticatedTestCase extends WebTestCase
{
    /**
     * A browser pre-authenticated as the given user (the founder by default). Tests asserting the
     * unauthenticated path should call static::createClient() directly instead.
     */
    protected function authenticatedClient(?User $user = null): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser($user ?? UserFactory::founder());

        return $client;
    }
}
