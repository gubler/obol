<?php

// ABOUTME: Twig `money` filter rendering a Money in the currency's convention for the ambient locale.
// ABOUTME: Delegates to MoneyFormatter, keeping call sites as {{ amount|money }} with no locale threading.

declare(strict_types=1);

namespace App\Twig;

use App\Service\Money\MoneyFormatter;
use App\ValueObject\Money;
use Twig\Attribute\AsTwigFilter;

final readonly class MoneyExtension
{
    public function __construct(
        private MoneyFormatter $moneyFormatter,
    ) {
    }

    #[AsTwigFilter(name: 'money')]
    public function format(Money $money): string
    {
        return $this->moneyFormatter->format($money);
    }
}
