<?php

// ABOUTME: A weekly snapshot of the total monthly obligation, stored native per-currency as a JSON map.
// ABOUTME: Native amounts are not converted, so a row survives subscription deletion and carries no FX. See ADR-0010.

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ObligationSnapshotRepository;
use Assert\Assertion;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ObligationSnapshotRepository::class)]
class ObligationSnapshot
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    /**
     * The native per-currency monthly obligation: currency code (e.g. "USD") to the summed
     * monthly-equivalent obligation in that currency's minor units (e.g. cents). Stored unconverted;
     * conversion to a display currency happens at read time using today's rate (ADR-0010).
     *
     * @var array<string, int>
     */
    #[ORM\Column(type: Types::JSON)]
    public private(set) array $obligationsByCurrency;

    /**
     * @param array<string, int> $obligationsByCurrency
     */
    public function __construct(
        array $obligationsByCurrency,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        public private(set) \DateTimeImmutable $recordedAt = new \DateTimeImmutable('today'),
    ) {
        foreach ($obligationsByCurrency as $amount) {
            Assertion::greaterOrEqualThan(value: $amount, limit: 0, message: 'Obligation amount cannot be negative');
        }

        $this->id = new Ulid();
        $this->obligationsByCurrency = $obligationsByCurrency;
    }
}
