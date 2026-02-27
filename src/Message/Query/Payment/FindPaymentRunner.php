<?php

// ABOUTME: Runner for FindPaymentQuery that retrieves a single payment by ID.
// ABOUTME: Returns Payment entity or null if not found or ID is invalid.

declare(strict_types=1);

namespace App\Message\Query\Payment;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'query.bus', handles: FindPaymentQuery::class)]
final readonly class FindPaymentRunner
{
    public function __construct(
        private PaymentRepository $paymentRepository,
    ) {
    }

    public function __invoke(FindPaymentQuery $query): ?Payment
    {
        if (!Ulid::isValid($query->paymentId)) {
            return null;
        }

        return $this->paymentRepository->find(Ulid::fromString($query->paymentId));
    }
}
