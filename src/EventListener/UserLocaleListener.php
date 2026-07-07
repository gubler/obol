<?php

// ABOUTME: Applies the authenticated user's locale so translation + money/number formatting follow it.
// ABOUTME: When the locale is unresolved, infers it from the browser once and persists it.

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Lib\Bus\CommandBus;
use App\Message\Command\User\ResolveUserLocaleCommand;
use App\Service\LocaleGuesser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

// Priority 7: after the firewall (8) so the user is available, and after Symfony's LocaleAwareListener
// (15) so LocaleSwitcher::setLocale is the last word on the request locale. Runs on every main request,
// so nothing bleeds between requests in the long-lived (FrankenPHP) worker.
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class UserLocaleListener
{
    public function __construct(
        private Security $security,
        private LocaleSwitcher $localeSwitcher,
        private LocaleGuesser $localeGuesser,
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // Unauthenticated: leave the framework default locale (en) in place.
            return;
        }

        $locale = $user->locale;
        if (null === $locale) {
            // First authenticated request after the locale was left unresolved: infer it from the
            // browser and persist once (via the command bus - data access stays in the handler layer,
            // ADR-0006), so it becomes the user's stored default (editable later in account settings).
            // A single write per user - the column is non-null forever after.
            $locale = $this->localeGuesser->guessFrom($event->getRequest());
            $this->commandBus->dispatch(new ResolveUserLocaleCommand($user->id, $locale));
        }

        // One first-party call: sets \Locale::setDefault (money/date formatting), the translator, and
        // the routing context - so we never touch the \Locale functions directly.
        $this->localeSwitcher->setLocale($locale);
    }
}
