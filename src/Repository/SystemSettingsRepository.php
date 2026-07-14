<?php

// ABOUTME: Doctrine repository for the SystemSettings singleton; get() is the only sanctioned accessor.
// ABOUTME: Owns the fixed id (1); the row is seeded by the creating migration, so get() never returns null.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SystemSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemSettings>
 */
class SystemSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemSettings::class);
    }

    /**
     * The single settings row. It is seeded by the migration that creates the table, so it always
     * exists - a missing row is a broken deploy, surfaced loudly here rather than silently defaulted.
     * This is the only accessor callers use; the inherited Doctrine finders are not (see ADR-0020).
     */
    public function get(): SystemSettings
    {
        $settings = $this->find(1);

        if (!$settings instanceof SystemSettings) {
            throw new \RuntimeException('The system_settings singleton row is missing; the creating migration seeds it.');
        }

        return $settings;
    }
}
