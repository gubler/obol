<?php

// ABOUTME: Architecture tests enforcing structural rules across src/ via reflection and source scans.
// ABOUTME: Replaces the former Pest arch() plugin; the "no debug functions" rule moved to PHPStan
// ABOUTME: (Symplify ForbiddenFuncCallRule in phpstan.dist.neon). See reference/adr/0006 and 0007.

declare(strict_types=1);

namespace App\Tests\Arch;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ArchTest extends TestCase
{
    private const string SRC = __DIR__ . '/../../src';

    public function testControllersAreSuffixedController(): void
    {
        foreach (self::classNamesUnder('App\Controller') as $class) {
            self::assertStringEndsWith(
                'Controller',
                $class,
                $class . ' is in App\Controller but is not suffixed "Controller"',
            );
        }
    }

    public function testRepositoriesAreSuffixedRepository(): void
    {
        foreach (self::classNamesUnder('App\Repository') as $class) {
            self::assertStringEndsWith(
                'Repository',
                $class,
                $class . ' is in App\Repository but is not suffixed "Repository"',
            );
        }
    }

    public function testEnumsAreBacked(): void
    {
        $enums = self::classNamesUnder('App\Enum');
        self::assertNotEmpty($enums, 'expected at least one enum under App\Enum');

        foreach ($enums as $enum) {
            self::assertTrue(enum_exists($enum), $enum . ' in App\Enum is not an enum');
            self::assertTrue(
                new \ReflectionEnum($enum)->isBacked(),
                $enum . ' must be a backed enum',
            );
        }
    }

    public function testEntitiesDoNotDependOnControllers(): void
    {
        foreach (self::filesUnder('App\Entity') as $path) {
            self::assertStringNotContainsString(
                'App\Controller',
                (string) file_get_contents($path),
                basename($path) . ' (App\Entity) must not depend on App\Controller',
            );
        }
    }

    /**
     * ADR-0006 / ADR-0007: data access (repositories + the EntityManager) is confined to the
     * handler layer. The repositories and Doctrine\ORM\EntityManagerInterface may only be
     * referenced from App\Message (handlers/runners/scheduler), App\Entity (repositoryClass
     * metadata) and App\Repository itself.
     */
    public function testDataAccessIsConfinedToTheHandlerLayer(): void
    {
        $allowedPrefixes = ['App\Message', 'App\Entity', 'App\Repository'];

        // Individually-justified framework-integration exemptions that genuinely cannot route through
        // the bus. Each entry carries its own justification.
        $namedExemptions = [
            // The security user provider runs at the pre-firewall bootstrap seam (refreshUser rehydrates
            // the session user on every request while security is still initializing); routing it through
            // the query bus would risk circularity once owner-scoped runners read the current user. It is
            // a data-access seam like a repository, not a consumer bypassing the boundary. See ADR-0014.
            \App\Security\MultiEmailUserProvider::class,
        ];

        foreach (self::allSourceFiles() as $path) {
            $namespace = self::namespaceOf($path);

            if (\in_array(self::fqcnOf($path), $namedExemptions, true)) {
                continue;
            }

            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($namespace, $prefix)) {
                    continue 2;
                }
            }

            $source = (string) file_get_contents($path);

            self::assertStringNotContainsString(
                'EntityManagerInterface',
                $source,
                $namespace . '\\' . basename($path, '.php') . ' must not use the EntityManager (data access is confined to the handler layer; see ADR-0006/0007)',
            );
            self::assertStringNotContainsString(
                'App\Repository\\',
                $source,
                $namespace . '\\' . basename($path, '.php') . ' must not use a repository (data access is confined to the handler layer; see ADR-0006/0007)',
            );
        }
    }

    /**
     * ADR-0015: subscription and payment data is per-user. Every command and query that reads or
     * mutates it carries an `ownerUserId` (a Ulid, per ADR-0007) so the handler can scope by owner;
     * this test makes that structural, so a new owned message cannot forget the owner.
     */
    public function testOwnedMessagesCarryTheOwnerUserId(): void
    {
        // Message namespaces whose data is owner-scoped. Every command and query touching per-user data
        // must carry an ownerUserId so the handler can scope by owner.
        $ownedNamespaces = [
            'App\Message\Command\Subscription',
            'App\Message\Query\Subscription',
            'App\Message\Command\Payment',
            'App\Message\Query\Payment',
            'App\Message\Query\Report',
            'App\Message\Command\Category',
            'App\Message\Query\Category',
            'App\Message\Command\PaymentSource',
            'App\Message\Query\PaymentSource',
        ];

        // Global jobs that legitimately span every user, each documented at its call site:
        $exemptions = [
            // the nightly generation sweep iterates all users' due subscriptions (owner is stamped on
            // each payment from its subscription).
            \App\Message\Command\Payment\GenerateDuePaymentsCommand::class,
        ];

        $checked = 0;
        foreach (self::classNamesUnder('App\Message') as $class) {
            if (!str_ends_with($class, 'Command') && !str_ends_with($class, 'Query')) {
                continue;
            }

            $inScope = array_any($ownedNamespaces, fn ($namespace) => str_starts_with($class, $namespace . '\\'));
            if (!$inScope) {
                continue;
            }

            if (\in_array($class, $exemptions, true)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            self::assertTrue(
                $reflection->hasProperty('ownerUserId'),
                $class . ' is an owner-scoped message but does not carry an ownerUserId (see ADR-0015)',
            );

            $property = $reflection->getProperty('ownerUserId');
            self::assertTrue($property->isPublic(), $class . '::$ownerUserId must be public');

            $type = $property->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame(
                Ulid::class,
                $type->getName(),
                $class . '::$ownerUserId must be a Ulid (messages carry Ulid, not strings; see ADR-0007)',
            );

            ++$checked;
        }

        self::assertGreaterThan(0, $checked, 'expected at least one owner-scoped message to check');
    }

    /**
     * Fully-qualified names of declared classes/enums whose namespace starts with the given prefix.
     *
     * @return list<class-string>
     */
    private static function classNamesUnder(string $namespacePrefix): array
    {
        $names = [];

        foreach (self::allSourceFiles() as $path) {
            $fqcn = self::fqcnOf($path);

            if (!str_starts_with($fqcn, $namespacePrefix . '\\')) {
                continue;
            }

            if (class_exists($fqcn) || enum_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn)) {
                $names[] = $fqcn;
            }
        }

        return $names;
    }

    /**
     * Absolute paths of source files whose namespace starts with the given prefix.
     *
     * @return list<string>
     */
    private static function filesUnder(string $namespacePrefix): array
    {
        $paths = [];

        foreach (self::allSourceFiles() as $path) {
            if (str_starts_with(self::namespaceOf($path), $namespacePrefix)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /** @return list<string> absolute paths to every .php file under src/ */
    private static function allSourceFiles(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::srcRoot(), \FilesystemIterator::SKIP_DOTS),
        );

        $paths = [];

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }

    private static function srcRoot(): string
    {
        return (string) realpath(self::SRC);
    }

    private static function fqcnOf(string $absolutePath): string
    {
        $relative = substr($absolutePath, \strlen(self::srcRoot()) + 1, -4);

        return 'App\\' . str_replace('/', '\\', $relative);
    }

    private static function namespaceOf(string $absolutePath): string
    {
        $fqcn = self::fqcnOf($absolutePath);
        $lastSeparator = strrpos($fqcn, '\\');

        return false === $lastSeparator ? $fqcn : substr($fqcn, 0, $lastSeparator);
    }
}
