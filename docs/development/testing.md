# Testing

Obol uses Pest PHP as the test runner (built on PHPUnit) with three test suites, Foundry factories for test data, and DAMA DoctrineTestBundle for automatic transaction rollback.

## Test Suites

| Suite | Directory | Base Class | Purpose |
|-------|-----------|-----------|---------|
| Unit | `tests/Unit/` | PHPUnit `TestCase` | Pure PHP, no DB, no HTTP |
| Feature | `tests/Feature/` | Symfony `WebTestCase` | HTTP layer via Symfony test client |
| Integration | `tests/Integration/` | Symfony `WebTestCase` | End-to-end workflows, real DB |

The `WebTestCase` binding for Feature and Integration suites is configured in `tests/Pest.php`:

```php
pest()->extend(WebTestCase::class)->in('Feature', 'Integration');
```

## Running Tests

```bash
mise run test                          # All tests (compact output)
mise run test:v                        # All tests (verbose output)
mise run test --testsuite=Unit         # Unit tests only
mise run test --testsuite=Feature      # Feature tests only
mise run test --testsuite=Integration  # Integration tests only
mise run test tests/Unit/SomeTest.php  # Single file
mise run test --filter="subscription"  # Filter by name
```

## Test Database

Tests use SQLite, not PostgreSQL. The `tests/bootstrap.php` script:

1. Drops the SQLite test database if it exists
2. Creates a fresh database file
3. Runs all Doctrine migrations

This happens once per test suite run. Individual tests do not re-migrate.

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

`tests/Arch/ArchTest.php` uses Pest's `arch()` helper to enforce structural rules:

- All classes in `App\Controller` must have `Controller` suffix
- Entities must not depend on controllers
- No debugging functions (`dump`, `dd`, `var_dump`, `print_r`, `ray`) in `App\`
- All enums must be backed
- All classes in `App\Repository` must have `Repository` suffix

## Code Coverage

Coverage is enforced at a **70% minimum** via `--min=70`:

```bash
mise run coverage         # Run with coverage check
mise run coverage:report  # Generate HTML report in var/coverage/
```

The threshold is set conservatively and should be ratcheted up over time. To increase it, update `--min=N` in both `mise.toml` and `.gitea/workflows/ci.yml`.

## Test Output

Tests must produce clean output. `phpunit.dist.xml` is configured with:

- `failOnDeprecation="true"`
- `failOnNotice="true"`
- `failOnWarning="true"`

If expected log output includes errors, those must be captured and asserted.
