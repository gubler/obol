<?php

// ABOUTME: Runner for FindAllPaymentSourcesQuery that retrieves all payment sources.
// ABOUTME: Returns an array of PaymentSource entities ordered by name.

declare(strict_types=1);

namespace App\Message\Query\PaymentSource;

use App\Entity\PaymentSource;
use App\Repository\PaymentSourceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindAllPaymentSourcesQuery::class)]
final readonly class FindAllPaymentSourcesRunner
{
    public function __construct(
        private PaymentSourceRepository $paymentSourceRepository,
    ) {
    }

    /**
     * @return array<PaymentSource>
     */
    public function __invoke(FindAllPaymentSourcesQuery $query): array
    {
        return $this->paymentSourceRepository->findBy([], ['name' => 'ASC']);
    }
}
