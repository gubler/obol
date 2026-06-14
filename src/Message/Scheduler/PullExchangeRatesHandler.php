<?php

// ABOUTME: Fetches the latest EUR-pivot rates and appends one ExchangeRate row per supported currency.
// ABOUTME: Idempotent per day - skips any currency already stored for the snapshot's date.

declare(strict_types=1);

namespace App\Message\Scheduler;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Repository\ExchangeRateRepository;
use App\Service\ExchangeRateProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: PullExchangeRatesMessage::class)]
final readonly class PullExchangeRatesHandler
{
    public function __construct(
        private ExchangeRateProviderInterface $rateProvider,
        private ExchangeRateRepository $exchangeRateRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(PullExchangeRatesMessage $message): void
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
