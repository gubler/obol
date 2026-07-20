<?php

// ABOUTME: Architecture tests enforcing structural rules across src/ via reflection and source scans.
// ABOUTME: Replaces the former Pest arch() plugin; the "no debug functions" rule moved to PHPStan
// ABOUTME: (Symplify ForbiddenFuncCallRule in phpstan.dist.neon). See reference/adr/0006 and 0007.

declare(strict_types=1);

namespace App\Tests\Arch;

use App\Entity\ExchangeRate;
use App\Entity\ObligationSnapshot;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ArchTest extends TestCase
{
    private const string SRC = __DIR__ . '/../../src';

    /**
     * ADR-0021: the calendar-date fields are the CalendarDate value object, not a bare instant. This is
     * the structural core of the naive/zoned fix - a reflection guard so a field can never silently
     * regress to a \DateTimeImmutable.
     */
    public function testCalendarDateFieldsAreTheValueObject(): void
    {
        self::assertSame(CalendarDate::class, self::propertyType(Subscription::class, 'nextRenewal'));
        self::assertSame('int', self::propertyType(Subscription::class, 'renewalDay'));
        self::assertSame(CalendarDate::class, self::propertyType(Payment::class, 'paidDate'));
        self::assertSame(CalendarDate::class, self::propertyType(ObligationSnapshot::class, 'recordedAt'));
        self::assertSame(CalendarDate::class, self::propertyType(ExchangeRate::class, 'asOf'));
    }

    /**
     * ADR-0021: only the two boundary types - User (owner's zone) and CalendarDate (the naive/zoned
     * valve) - may touch a timezone inside the domain. Any other entity or value object manipulating a
     * zone is reintroducing exactly the naive-vs-zoned confusion the CalendarDate type exists to prevent.
     */
    public function testOnlyTheBoundaryTypesManipulateTimezones(): void
    {
        $allowed = ['User.php', 'CalendarDate.php'];

        foreach ([...self::filesUnder('App\Entity'), ...self::filesUnder('App\ValueObject')] as $path) {
            if (\in_array(basename($path), $allowed, true)) {
                continue;
            }

            $source = (string) file_get_contents($path);
            foreach (['new \DateTimeZone', '->setTimezone(', 'date_default_timezone_'] as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path) . ' manipulates a timezone; only User and CalendarDate may (ADR-0021)',
                );
            }
        }
    }

    /**
     * ADR-0021: turning a date into a `Y-m-d` string is CalendarDate's job (its `__toString`), so no
     * other file hand-rolls `->format('Y-m-d')` - that ad-hoc reduction is how a zoned instant used to
     * leak in as a naive string.
     */
    public function testNoAdHocYmdFormattingOutsideCalendarDate(): void
    {
        foreach (self::allSourceFiles() as $path) {
            if ('CalendarDate.php' === basename($path)) {
                continue;
            }

            self::assertStringNotContainsString(
                "->format('Y-m-d')",
                (string) file_get_contents($path),
                basename($path) . " must not hand-roll ->format('Y-m-d'); cast a CalendarDate instead (ADR-0021)",
            );
        }
    }

    /**
     * ADR-0021: a past renewal is allowed (it starts Manual generation); the future-date validation was
     * deleted, so no DTO re-adds a `GreaterThan('today')` constraint.
     */
    public function testNoFutureDateConstraintInDtos(): void
    {
        foreach (self::filesUnder('App\Dto') as $path) {
            self::assertStringNotContainsString(
                "GreaterThan(value: 'today'",
                (string) file_get_contents($path),
                basename($path) . ' must not constrain a date to the future (past dates are allowed; ADR-0021)',
            );
        }
    }

    /**
     * ADR-0021: entities never read the clock. "Now" is passed in (a `$now` instant) or resolved by a
     * handler from the injected ClockInterface, so entity behavior stays deterministic and testable.
     */
    public function testEntitiesDoNotDependOnTheClock(): void
    {
        foreach (self::filesUnder('App\Entity') as $path) {
            self::assertStringNotContainsString(
                'ClockInterface',
                (string) file_get_contents($path),
                basename($path) . ' must not depend on the clock; a $now instant is passed in (ADR-0021)',
            );
        }
    }

    /**
     * ADR-0021: time-sensitive code reads "now" from the injected clock or a passed instant, never the
     * ambient process clock. The sole exception is an audit-timestamp field defaulting to now at the
     * moment of construction (createdAt and its kin), which is instant-storage, not a time-sensitive
     * decision.
     */
    public function testTimeSensitiveCodeDoesNotReadTheAmbientClock(): void
    {
        $auditFields = ['createdAt', 'verifiedAt', 'lastUsedAt', 'onboardingCompletedAt'];

        foreach ([...self::filesUnder('App\Entity'), ...self::filesUnder('App\Message')] as $path) {
            foreach (explode("\n", (string) file_get_contents($path)) as $line) {
                if (!str_contains($line, 'new \DateTimeImmutable()')) {
                    continue;
                }

                $isAuditDefault = array_any($auditFields, static fn (string $field): bool => str_contains($line, $field));
                self::assertTrue(
                    $isAuditDefault,
                    basename($path) . ' reads the ambient clock (argless new \DateTimeImmutable()); inject a clock or pass an instant (ADR-0021)',
                );
            }
        }
    }

    private static function propertyType(string $class, string $property): string
    {
        $type = new \ReflectionProperty($class, $property)->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);

        return $type->getName();
    }

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

    public function testValueObjectsAreFinalAndReadonly(): void
    {
        $valueObjects = self::classNamesUnder('App\ValueObject');
        self::assertNotEmpty($valueObjects, 'expected at least one value object under App\ValueObject');

        foreach ($valueObjects as $class) {
            $reflection = new \ReflectionClass($class);
            self::assertTrue($reflection->isFinal(), $class . ' in App\ValueObject must be final');
            self::assertTrue($reflection->isReadOnly(), $class . ' in App\ValueObject must be readonly');
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
     * ADR-0020: the SystemSettings singleton is read only through SystemSettingsRepository::get(), which
     * owns the find(1). No consumer reaches for the inherited Doctrine finders on that repository, so the
     * "there is exactly one row" contract keeps a single, legible accessor.
     */
    public function testSystemSettingsIsAccessedOnlyViaGet(): void
    {
        $repositoryFile = self::srcRoot() . '/Repository/SystemSettingsRepository.php';

        foreach (self::allSourceFiles() as $path) {
            // get() itself calls find(1); the rule constrains consumers, not the accessor.
            if ($path === $repositoryFile) {
                continue;
            }

            $source = (string) file_get_contents($path);
            if (!str_contains($source, 'SystemSettingsRepository')) {
                continue;
            }

            foreach (['->find(', '->findBy(', '->findOneBy(', '->findAll('] as $finder) {
                self::assertStringNotContainsString(
                    $finder,
                    $source,
                    basename($path) . ' uses a raw Doctrine finder where it references SystemSettingsRepository; read the singleton through get() instead (ADR-0020)',
                );
            }
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

            $inScope = array_any($ownedNamespaces, fn (string $namespace) => str_starts_with($class, $namespace . '\\'));
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
