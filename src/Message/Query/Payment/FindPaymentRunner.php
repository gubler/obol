<?php

// ABOUTME: Runner for FindPaymentQuery that retrieves a single payment by ID.
// ABOUTME: Returns the Payment entity, or null when not found.

declare(strict_types=1);

namespace App\Message\Query\Payment;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindPaymentQuery::class)]
final readonly class FindPaymentRunner
{
    public function __construct(
        private PaymentRepository $paymentRepository,
    ) {
    }

    public function __invoke(FindPaymentQuery $query): ?Payment
    {
        return $this->paymentRepository->find($query->paymentId);
    }
}
