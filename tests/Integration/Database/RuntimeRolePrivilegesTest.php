<?php

// ABOUTME: Proves the database privilege split by exercising it: the application connects as a role
// ABOUTME: that cannot change the schema, and migrations connect as the role that owns it.

declare(strict_types=1);

namespace App\Tests\Integration\Database;

use App\Doctrine\Migrations\EntitySchemaProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RuntimeRolePrivilegesTest extends WebTestCase
{
    /**
     * Asserted first and on its own, because every other test here reads as a mystery when the two
     * connections have collapsed onto one role - a cluster provisioned before the split, or a
     * DATABASE_URL still naming the owner. Naming that case is what turns a wall of permission
     * failures into one instruction.
     */
    public function testTheApplicationAndMigrationConnectionsUseDifferentRoles(): void
    {
        self::assertNotSame(
            $this->currentUser('default'),
            $this->currentUser('migrations'),
            'The application and migration connections resolved to the same database role, so nothing '
            . 'below can prove anything. Provision the split with `mise run db:roles`.',
        );
    }

    public function testTheRuntimeRoleCannotCreateATable(): void
    {
        self::assertMatchesRegularExpression(
            '/permission denied|must be owner/i',
            $this->refusalOf('CREATE TABLE runtime_ddl_probe (id INT NOT NULL)'),
        );
    }

    public function testTheRuntimeRoleCannotAlterATable(): void
    {
        self::assertMatchesRegularExpression(
            '/permission denied|must be owner/i',
            $this->refusalOf('ALTER TABLE subscription ADD COLUMN runtime_ddl_probe INT'),
        );
    }

    public function testTheRuntimeRoleCannotDropATable(): void
    {
        self::assertMatchesRegularExpression(
            '/permission denied|must be owner/i',
            $this->refusalOf('DROP TABLE subscription'),
        );
    }

    /**
     * The rule that would otherwise rot silently. PostgreSQL grants the runtime role nothing on a
     * table created after it, so without default privileges every future migration that adds one
     * breaks the application at runtime - and presents as an application bug rather than a
     * permissions problem. This creates a table the way a migration does and then uses it the way a
     * request does, with no grant in between.
     */
    public function testATableTheOwnerCreatesIsImmediatelyUsableByTheRuntimeRole(): void
    {
        $owner = $this->independentConnection('migrations');
        $owner->executeStatement('CREATE TABLE default_privilege_probe (id INT NOT NULL)');

        try {
            $runtime = $this->independentConnection('default');

            $runtime->executeStatement('INSERT INTO default_privilege_probe (id) VALUES (1)');
            $runtime->executeStatement('UPDATE default_privilege_probe SET id = 2');
            self::assertSame(2, (int) $runtime->fetchOne('SELECT id FROM default_privilege_probe'));

            $runtime->executeStatement('DELETE FROM default_privilege_probe');
            self::assertSame(0, (int) $runtime->fetchOne('SELECT count(*) FROM default_privilege_probe'));

            $runtime->close();
        } finally {
            $owner->executeStatement('DROP TABLE default_privilege_probe');
            $owner->close();
        }
    }

    public function testMigrationsRunAsTheOwnerRole(): void
    {
        self::assertSame(
            $this->currentUser('migrations'),
            (string) $this->migrations()->getConnection()->fetchOne('SELECT current_user'),
        );
    }

    /**
     * Selecting a connection for migrations costs the dependency factory its entity manager, and with
     * it `doctrine:migrations:diff` - which fails at the point someone reaches for it, long after the
     * change that broke it. See EntitySchemaProvider.
     */
    public function testDiffStillHasTheEntityMappingToCompareAgainst(): void
    {
        $factory = $this->migrations();

        self::assertTrue($factory->hasSchemaProvider());
        self::assertInstanceOf(EntitySchemaProvider::class, $factory->getSchemaProvider());
    }

    /**
     * Returns the error the runtime role is refused with, and fails the test outright if it was not
     * refused at all.
     */
    private function refusalOf(string $sql): string
    {
        // Deliberately not the container's connection: a statement that fails poisons the
        // transaction DAMA wraps every test in, which would take the rest of the case down with it.
        $connection = $this->independentConnection('default');

        try {
            $connection->executeStatement($sql);
        } catch (DriverException $driverException) {
            return $driverException->getMessage();
        } finally {
            $connection->close();
        }

        self::fail(\sprintf('The runtime role was allowed to run "%s", so it can still change the schema.', $sql));
    }

    private function currentUser(string $connection): string
    {
        return (string) $this->connection($connection)->fetchOne('SELECT current_user');
    }

    /**
     * A connection built from the container connection's parameters but outside the container, so it
     * carries none of DAMA's middleware and commits for real. Both halves matter here: the DDL probes
     * must not poison the surrounding test transaction, and the default-privilege probe has to be
     * visible to a second connection.
     */
    private function independentConnection(string $name): Connection
    {
        return DriverManager::getConnection($this->connection($name)->getParams());
    }

    private function connection(string $name): Connection
    {
        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get(id: 'doctrine');

        $connection = $registry->getConnection($name);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function migrations(): DependencyFactory
    {
        $factory = self::getContainer()->get(id: 'doctrine.migrations.dependency_factory');
        self::assertInstanceOf(DependencyFactory::class, $factory);

        return $factory;
    }
}
