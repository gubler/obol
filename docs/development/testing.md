# Testing

Obol uses PHPUnit as the test runner with four test suites, Foundry factories for test data, and DAMA DoctrineTestBundle for automatic transaction rollback.

## Test Suites

| Suite | Directory | Base Class | Purpose |
|-------|-----------|-----------|---------|
| Unit | `tests/Unit/` | PHPUnit `TestCase` | Pure PHP, no DB, no HTTP |
| Feature | `tests/Feature/` | Symfony `WebTestCase` | HTTP layer via Symfony test client |
| Integration | `tests/Integration/` | Symfony `WebTestCase` | End-to-end workflows, real DB |
| Arch | `tests/Arch/` | PHPUnit `TestCase` | Structural rules over `src/` |

Test classes are namespaced under `App\Tests\` (PSR-4 maps `App\Tests\` to `tests/`). Feature and Integration classes extend `WebTestCase` directly; Unit and Arch classes extend `TestCase`.

## Running Tests

```bash
mise run test                             # All tests
mise run test:v                           # All tests (testdox output)
mise run test -- --testsuite=Unit         # Unit tests only
mise run test -- --testsuite=Feature      # Feature tests only
mise run test -- --testsuite=Integration  # Integration tests only
mise run test -- tests/Unit/SomeTest.php  # Single file
mise run test -- --filter=subscription    # Filter by name
```

## Test Database

Tests run against PostgreSQL (the same engine as production). The `tests/bootstrap.php` script:

1. Drops the test database if it exists
2. Creates a fresh database
3. Runs all Doctrine migrations

This happens once per test suite run. Individual tests do not re-migrate. DAMA wraps each test in a transaction (see below), so the schema is built once and reused.

## DAMA DoctrineTestBundle

Each test is wrapped in a database transaction that rolls back after the test completes. This means:

- Tests can create, modify, and delete data without cleanup
- Tests are fully isolated from each other
- No fixture loading is needed — tests create their own data via Foundry

## Foundry Factories

Located in `src/Factory/` (shared between tests and fixtures):

| Factory | Entity | Notable States |
|---------|--------|---------------|
| `CategoryFactory` | `Category` | — |
| `SubscriptionFactory` | `Subscription` | `archived()`, `withRecentPayment()`, `expensiveSubscription()` |
| `PaymentFactory` | `Payment` | — |
| `SubscriptionEventFactory` | `SubscriptionEvent` | — |

Usage in tests:

```php
use App\Factory\SubscriptionFactory;
use App\Factory\CategoryFactory;

$category = CategoryFactory::createOne(['name' => 'Streaming']);
$subscription = SubscriptionFactory::createOne([
    'category' => $category,
    'name' => 'Netflix',
]);
```

## Architecture Tests

`tests/Arch/ArchTest.php` enforces structural rules with plain PHPUnit tests that reflect over and scan `src/`:

- All classes in `App\Controller` must have a `Controller` suffix
- All classes in `App\Repository` must have a `Repository` suffix
- All enums in `App\Enum` must be backed
- Entities must not depend on controllers
- Data access (repositories and the `EntityManager`) is confined to the handler layer — only `App\Message`, `App\Entity` and `App\Repository` may reference them (see [ADR-0006](../../reference/adr/0006-cqrs-buses-data-access-boundary.md) / [ADR-0007](../../reference/adr/0007-write-path-message-conventions.md))

The "no debugging functions" rule (`dump`, `dd`, `var_dump`, `print_r`, `ray`) is enforced by PHPStan instead — function-call rules are not something reflection can express. It lives in the Symplify `ForbiddenFuncCallRule` in `phpstan.dist.neon` and runs under `mise run sa`.

## Static Analysis of Tests

`tests/` is analysed by a separate, relaxed PHPStan profile (`phpstan-tests.neon`, run via `mise run sa:tests`) — a lower level than the strict `src/` profile, with type-coverage off and the deliberate test-guard patterns ignored. It lights up `phpstan/phpstan-phpunit` over the test code. The strict `src/` profile (`mise run sa`) does not analyse `tests/`.

## JavaScript (Stimulus) tests

The Stimulus controllers are tested separately, with [Vitest](https://vitest.dev/) + jsdom rather than the PHP suite - the JS analog of the PHPUnit tests. Specs are named `*.test.js` and live next to the controller they cover (e.g. `assets/controllers/conditional_field_controller.test.js`). They mount the controller on a fixture element through a real Stimulus `Application` and assert behavior via the DOM, not by calling private methods.

```bash
mise run js:test   # Vitest, host-side via npm
```

This runs in `mise run check`, the git hooks, and CI alongside the PHP tests. See [Frontend](../frontend.md#javascript-toolchain-dev-only) for the full JS toolchain (Biome, Vitest, `tsc --checkJs`).

## Code Coverage

Coverage is enforced at a **70% minimum**. PHPUnit has no native threshold flag (Pest's `--min` did), so `bin/coverage-min.php` reads the Clover report and fails the build when line coverage falls below the minimum:

```bash
mise run coverage         # Run with coverage check (min 70%)
mise run coverage:report  # Generate HTML report in var/coverage/
```

The threshold is set conservatively and should be ratcheted up over time. To increase it, update the minimum passed to `bin/coverage-min.php` in both `mise.toml` (the `coverage` task) and `.gitea/workflows/ci.yml` (the PHPUnit step).

## Test Output

Tests must produce clean output. `phpunit.dist.xml` is configured with:

- `failOnDeprecation="true"`
- `failOnNotice="true"`
- `failOnWarning="true"`

If expected log output includes errors, those must be captured and asserted.
