<?php

// ABOUTME: Builds the schema the entity mapping describes, so doctrine:migrations:diff has something
// ABOUTME: to compare the database against now that migrations run on a bare connection.

declare(strict_types=1);

namespace App\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Pointing doctrine_migrations at a connection rather than an entity manager (it runs as the owner
 * role, which the application's entity manager is not) leaves the migrations dependency factory with
 * no way to reach the ORM - and `diff` compares the mapping against the database, so it would stop
 * working. This is the bundle's supported seam for handing it one back.
 *
 * Nothing here touches the database: the schema is derived from mapping metadata alone, and the
 * comparison against the real schema happens on the migrations connection. The entity manager is the
 * application's own, which is what keeps `diff` quiet about the framework-owned tables - the
 * postGenerateSchema listeners that contribute `sessions`, `cache_items` and `messenger_messages` are
 * registered against it.
 *
 * Written rather than wiring Doctrine's own OrmSchemaProvider, which is marked @internal.
 */
final readonly class EntitySchemaProvider implements SchemaProvider
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function createSchema(): Schema
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        // Sorted by table name so a generated migration reads the same way twice, whatever order the
        // mapping driver happened to discover the entities in.
        usort($metadata, static fn (ClassMetadata $a, ClassMetadata $b): int => $a->getTableName() <=> $b->getTableName());

        return new SchemaTool($this->entityManager)->getSchemaFromMetadata($metadata);
    }
}
