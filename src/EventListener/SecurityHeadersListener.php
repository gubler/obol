<?php

// ABOUTME: Adds browser-hardening response headers (nosniff, clickjacking, referrer, CSP) to documents.
// ABOUTME: CSP is report-only for now; static-file nosniff is set at the Caddy edge, which PHP bypasses.

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Runs on every main response. The Symfony kernel only handles dynamic (PHP) responses; static files
// and uploaded logos are served by Caddy's file_server, so their nosniff header is set at the edge
// (frankenphp/Caddyfile) instead - a PHP listener never sees them.
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class SecurityHeadersListener
{
    // A report-only policy: the browser reports violations but blocks nothing, so this cannot break the
    // app. It is intentionally strict (the target we want to enforce). The inline importmap, the
    // Stimulus/Turbo bootstrap, and driver.js styles will report violations until the policy is tuned
    // (nonces/hashes) and switched to the enforcing `Content-Security-Policy` header.
    private const string CONTENT_SECURITY_POLICY = "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "frame-ancestors 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "object-src 'none'";

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Leave the dev web profiler and debug toolbar untouched - their own inline assets would only
        // add noise to the report-only CSP, and they never run in production.
        $route = $event->getRequest()->attributes->get('_route');
        if (\is_string($route) && (str_starts_with($route, '_wdt') || str_starts_with($route, '_profiler'))) {
            return;
        }

        $headers = $event->getResponse()->headers;

        // Enforced immediately - none of these change how a correct page renders.
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Report-only until tuned and enforced.
        $headers->set('Content-Security-Policy-Report-Only', self::CONTENT_SECURITY_POLICY);
    }
}
