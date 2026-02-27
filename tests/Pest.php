<?php

// ABOUTME: Pest PHP configuration file.
// ABOUTME: Binds test case classes to test directories.

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

pest()->extend(WebTestCase::class)->in('Feature', 'Integration');
