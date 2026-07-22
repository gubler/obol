<?php

// ABOUTME: Feature test that document responses carry the browser-hardening security headers.
// ABOUTME: nosniff, clickjacking (X-Frame-Options), Referrer-Policy, and a report-only CSP.

declare(strict_types=1);

namespace App\Tests\Feature\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SecurityHeadersTest extends WebTestCase
{
    public function testDocumentResponsesCarryTheSecurityHeaders(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');

        // CSP starts report-only: a strict policy risks breaking the inline importmap / Stimulus /
        // Turbo / driver.js until it is tuned. It must at least forbid framing and default to self.
        $csp = $client->getResponse()->headers->get('Content-Security-Policy-Report-Only');
        self::assertNotNull($csp, 'a report-only CSP must be present');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
    }
}
