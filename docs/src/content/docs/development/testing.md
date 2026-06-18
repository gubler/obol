---
title: "Testing"
---

Obol uses PHPUnit as the test runner with four test suites, Foundry factories for test data, and DAMA DoctrineTestBundle for automatic transaction rollback.

## Test Suites

| Suite | Directory | Base Class | Purpose |
|-------|-----------|-----------|---------|
| Unit | `tests/Unit/` | PHPUnit `TestCase` | Pure PHP, no DB, no HTTP |
| Feature | `tests/Feature/` | Symfony `WebTestCase` | HTTP layer via Symfony test client |
| Integration | `tests/Integration/` | Symfony `WebTestCase` / Panther `PantherTestCase` | End-to-end workflows, real DB; real-browser tests for JS behavior |
| Arch | `tests/Arch/` | PHPUnit `TestCase` | Structural rules over `src/` |

Test classes are namespaced under `App\Tests\` (PSR-4 maps `App\Tests\` to `tests/`). Feature and Integration classes extend `WebTestCase` directly; Unit and Arch classes extend `TestCase`. Browser tests in the Integration suite extend Panther's `PantherTestCase` (see [Browser tests](#browser-tests-panther)).

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
- Data access (repositories and the `EntityManager`) is confined to the handler layer — only `App\Message`, `App\Entity` and `App\Repository` may reference them (see [ADR-0006](https://code.dev88.work/dev88/obol/src/branch/main/reference/adr/0006-cqrs-buses-data-access-boundary.md) / [ADR-0007](https://code.dev88.work/dev88/obol/src/branch/main/reference/adr/0007-write-path-message-conventions.md))

The "no debugging functions" rule (`dump`, `dd`, `var_dump`, `print_r`, `ray`) is enforced by PHPStan instead — function-call rules are not something reflection can express. It lives in the Symplify `ForbiddenFuncCallRule` in `phpstan.dist.neon` and runs under `mise run sa`.

## Static Analysis of Tests

`tests/` is analysed by a separate, relaxed PHPStan profile (`phpstan-tests.neon`, run via `mise run sa:tests`) — a lower level than the strict `src/` profile, with type-coverage off and the deliberate test-guard patterns ignored. It lights up `phpstan/phpstan-phpunit` over the test code. The strict `src/` profile (`mise run sa`) does not analyse `tests/`.

## JavaScript (Stimulus) tests

The Stimulus controllers are tested separately, with [Vitest](https://vitest.dev/) + jsdom rather than the PHP suite - the JS analog of the PHPUnit tests. Specs are named `*.test.js` and live next to the controller they cover (e.g. `assets/controllers/conditional_field_controller.test.js`). They mount the controller on a fixture element through a real Stimulus `Application` and assert behavior via the DOM, not by calling private methods.

```bash
mise run js:test   # Vitest, host-side via npm
```

This runs in `mise run check`, the git hooks, and CI alongside the PHP tests. See [Frontend](../frontend.md#javascript-toolchain-dev-only) for the full JS toolchain (Biome, Vitest, `tsc --checkJs`).

## Browser tests (Panther)

Vitest covers a controller's logic against a hand-built DOM; what it cannot prove is that the controller actually loads and wires up against the real server-rendered page in a real browser. For that there is a thin layer of [Symfony Panther](https://symfony.com/doc/current/testing/end_to_end.html) tests in the Integration suite, driving headless Chromium over WebDriver. They are the JS analog of an end-to-end test: reserved for behavior that genuinely needs a browser, not a substitute for the Vitest/crawler pair.

The first one is `tests/Integration/Controller/Subscription/ColorSyncBrowserTest.php`. Chromium and chromedriver ship in the dev image already, so they run inside the `php` container with no extra setup; CI installs `chromium`/`chromium-driver` and sets the `PANTHER_*` knobs (#85).

The cross-process gotcha: Panther runs the app in its own PHP CLI server, so DAMA's per-test transaction rollback - which keeps the normal suite isolated - cannot reach it. A browser test therefore:

- carries `#[SkipDatabaseRollback]` (DAMA) and truncates the tables it touches in `setUp`/`tearDown`;
- seeds via Foundry in the test process, which **commits** (no surrounding transaction), so the rows are visible to the browser's server;
- carries `#[WithoutErrorHandler]` so PHPUnit's error handler does not trip on the browser interaction;
- passes `--headless=new --no-sandbox --disable-dev-shm-usage` to Chromium (root in a container with a small `/dev/shm`).

`PANTHER_APP_ENV=test` (in `.env.test`) makes the spawned server share the `app_test` database with the test process. Browser tests run wherever the PHP suite does - `mise run test`, the git hooks, and CI.

## Code Coverage

Coverage is enforced at a **70% minimum**. PHPUnit has no native threshold flag (Pest's `--min` did), so `bin/coverage-min.php` reads the Clover report and fails the build when line coverage falls below the minimum:

```bash
mise run coverage         # Run with coverage check (min 70%)
mise run coverage:report  # Generate HTML report in var/coverage/
```

The threshold is set conservatively and should be ratcheted up over time. To increase it, update the minimum passed to `bin/coverage-min.php` in both `mise.toml` (the `coverage` task) and `.gitea/workflows/ci.yml` (the PHPUnit step).

## Mutation Testing

Coverage measures which lines run; mutation testing measures whether the tests would actually _catch a bug_ in those lines. [Infection](https://infection.github.io/) systematically mutates `src/` (flips conditionals, swaps operators, removes return values) and re-runs the tests against each mutant. A mutant the tests still pass on is "escaped" — a hole in the suite. The Mutation Score Indicator (MSI) is the percentage of mutants the tests killed.

```bash
mise run infection                            # Mutation test the Unit suite
mise run infection -- --filter=Subscription.php   # One source file
```

Configuration lives in [`infection.json5`](https://code.dev88.work/dev88/obol/src/branch/main/infection.json5). Reports are written to the gitignored `var/infection/` (text, HTML, and a summary log).

**On-demand only.** Infection is deliberately absent from `mise run check`, the git hooks, and CI — it is a periodic rigor check, not a gate. A run takes ~90 seconds. The task targets the **Unit suite**: Feature and Integration are slow and DB/HTTP-bound, and the unit-tested domain logic (entities, enums, value objects) is the meaningful mutation target.

The baseline is **~79-81% MSI** (runs vary a couple of points). `minMsi` / `minCoveredMsi` are pinned at **75** in `infection.json5`, a safe margin under the baseline so an honest run never spuriously fails; ratchet them up as the suite improves.

Three non-default knobs are baked into the `mise run infection` task, each needed for a green run on this image:

- **`XDEBUG_MODE=coverage`** — the image ships Xdebug, not pcov; without a coverage driver Infection aborts.
- **`php -d memory_limit=-1`** — mutation analysis itself succeeds, but the post-run temp-file cleanup walks thousands of files through Symfony Finder and OOMs on the 128M CLI default.
- **`--threads=4`** — `--threads=max` exhausts the container file-descriptor limit under coverage and dies mid-run with "Too many open files"; 4 is the stable ceiling.

Infection 0.33 sits cleanly alongside PHPUnit 13 — it does not constrain `phpunit/phpunit`; its adapter detects the version at runtime.

## Test Output

Tests must produce clean output. `phpunit.dist.xml` is configured with:

- `failOnDeprecation="true"`
- `failOnNotice="true"`
- `failOnWarning="true"`

If expected log output includes errors, those must be captured and asserted.
