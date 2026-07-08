<?php

// ABOUTME: Routes authenticated users who have not finished first-run onboarding to /onboarding.
// ABOUTME: Runs on every main request so magic-link, passkey, and remember-me entry paths are all gated.

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST)]
final readonly class OnboardingGateListener
{
    /**
     * Paths reachable while onboarding is incomplete:
     * - the onboarding screen itself (the loop breaker) and logout (a way out);
     * - anything under `/_`: the profiler/wdt/error internals, plus the dev/test-only `/_test` login
     *   bypass (which 404s in prod) - none of which should ever be gated;
     * - `/assets`: in dev, AssetMapper serves the page's own CSS/JS through the kernel, so those
     *   requests hit this listener. In prod the assets are compiled to `public/assets` and served
     *   statically by the web server, bypassing the kernel (and this listener) entirely.
     *
     * @var list<string>
     */
    private const array ALLOWLIST_PREFIXES = ['/app/onboarding', '/logout', '/_', '/assets'];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->hasCompletedOnboarding()) {
            return;
        }

        if ($this->isAllowlisted($event->getRequest()->getPathInfo())) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('onboarding')));
    }

    private function isAllowlisted(string $path): bool
    {
        foreach (self::ALLOWLIST_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // The signed secondary-email verification link is public and must stay reachable.
        return 1 === preg_match('#^/account/emails/[0-9A-HJKMNP-TV-Z]{26}/verify$#', $path);
    }
}
