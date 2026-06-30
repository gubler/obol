<?php

// ABOUTME: Runner for FindPaymentSourceQuery that retrieves a single payment source by ID.
// ABOUTME: Returns the PaymentSource entity, or null when not found.

declare(strict_types=1);

namespace App\Message\Query\PaymentSource;

use App\Entity\PaymentSource;
use App\Repository\PaymentSourceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindPaymentSourceQuery::class)]
final readonly class FindPaymentSourceRunner
{
    public function __construct(
        private PaymentSourceRepository $paymentSourceRepository,
    ) {
    }

    public function __invoke(FindPaymentSourceQuery $query): ?PaymentSource
    {
        return $this->paymentSourceRepository->find($query->paymentSourceId);
    }
}
