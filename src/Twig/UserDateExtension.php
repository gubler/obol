<?php

// ABOUTME: Twig `user_date` filter rendering a date per the current user's DateFormat preference.
// ABOUTME: Explicit formats use a locale-independent ICU pattern; LocaleDefault follows the ambient locale.

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Enum\DateFormat;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class UserDateExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('user_date', $this->format(...)),
        ];
    }

    public function format(\DateTimeInterface $date): string
    {
        $user = $this->security->getUser();
        $dateFormat = $user instanceof User ? $user->dateFormat : DateFormat::LocaleDefault;

        // An explicit pattern (ISO/US/EU) fixes the digit order regardless of locale; a null pattern
        // (LocaleDefault) falls through to the MEDIUM length in the ambient locale (set by
        // UserLocaleListener). Timezone left null to match the prior format_date('medium') behavior.
        $pattern = $dateFormat->pattern();
        $formatter = new \IntlDateFormatter(
            $this->localeSwitcher->getLocale(),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            null,
            \IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        return (string) $formatter->format($date);
    }
}
