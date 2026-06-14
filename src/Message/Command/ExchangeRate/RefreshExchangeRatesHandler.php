<?php

// ABOUTME: Handler for RefreshExchangeRatesCommand: fetches the latest EUR-pivot rates and appends new rows.
// ABOUTME: Idempotent per day - skips any currency already stored for the snapshot's date. The command bus commits.

declare(strict_types=1);

namespace App\Message\Command\ExchangeRate;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Repository\ExchangeRateRepository;
use App\Service\ExchangeRateProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: RefreshExchangeRatesCommand::class)]
final readonly class RefreshExchangeRatesHandler
{
    public function __construct(
        private ExchangeRateProviderInterface $rateProvider,
        private ExchangeRateRepository $exchangeRateRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RefreshExchangeRatesCommand $command): void
    {
        $snapshot = $this->rateProvider->fetchLatest();

        foreach ($snapshot->rates as $code => $rate) {
            $currency = Currency::from($code);

            // Append-only and idempotent: one row per currency per day.
            if ($this->exchangeRateRepository->hasRateFor($currency, $snapshot->date)) {
                continue;
            }

            $this->entityManager->persist(new ExchangeRate($currency, $rate, $snapshot->date));
        }
    }
}
