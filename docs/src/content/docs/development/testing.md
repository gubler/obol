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

Every step is checked and a failure aborts the run (`tests/Support/TestDatabase.php`). The rebuild is the only thing separating one run from the next: the browser tests opt out of DAMA's rollback and commit, so a database that survives into the next run carries their writes with it - including a truncated `user` table, which strips the founder the migrations seed. Left unchecked, that surfaces later as assertion failures in whatever depends on that baseline, in tests that look unrelated to the browser suite and pass in isolation.

Postgres refuses to drop a database that still has a session attached, so the usual cause is a Panther PHP server orphaned by an interrupted run. Clear it and re-run:

```bash
./bin/dc exec -T php sh -c "pkill -9 -f 'S localhost:9080'; pkill -9 -f chromium; exit 0"
```

Note that `pkill -f "php -S"` does **not** match it - the command line is `php -dvariables_order=EGPCS -S localhost:9080`. To confirm the port is free:

```bash
curl -s -o /dev/null -w '%{http_code}' --max-time 3 http://localhost:9080/
```

`000` means free; `200` means a stray is still serving.

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
- Data access (repositories and the `EntityManager`) is confined to the handler layer — only `App\Message`, `App\Entity` and `App\Repository` may reference them (see ADR-0006 and ADR-0007)
- The calendar-date frame is locked in place (see ADR-0021): the five calendar-date fields are `CalendarDate`, only `User` and `CalendarDate` may touch a timezone, no code hand-rolls `->format('Y-m-d')`, no DTO constrains a date to the future, entities take no clock, and time-sensitive code never reads the ambient `new \DateTimeImmutable()`

The "no debugging functions" rule (`dump`, `dd`, `var_dump`, `print_r`, `ray`) is enforced by PHPStan instead — function-call rules are not something reflection can express. It lives in the Symplify `ForbiddenFuncCallRule` in `phpstan.dist.neon` and runs under `mise run sa`.

## Timezone and CalendarDate testing

Calendar-date behavior (`CalendarDate`, the renewal anchor, the payment date, report boundaries) is
timezone-sensitive at exactly one seam — the owner's zone, applied at read time. Two conventions keep
those tests honest:

- **Prove independence by contrast, not by pinning.** A test that a date resolves correctly in a given
  zone uses two owners in *contrasting* zones reading the same instant (e.g. `Pacific/Honolulu` at
  06:00 UTC is still the previous day, `Asia/Tokyo` is already the next), and asserts the days differ.
  Feature tests prefer permanent-offset zones with no DST (`Pacific/Kiritimati` UTC+14,
  `Pacific/Midway` UTC-11) so a DST transition can't move the answer.
- **`PinsDefaultTimezone` only to prove ambient-independence.** `CalendarDate` is designed to ignore the
  process default timezone. The `App\Tests\Support\PinsDefaultTimezone` trait sets a hostile ambient
  zone (and restores it afterward) so a test can *prove* the result doesn't move; it is not a substitute
  for passing an explicit zone in the code under test. `CalendarDateAssertions::assertSameDate()`
  compares a `CalendarDate` against an expected `Y-m-d` string by value.

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

Configuration lives in `infection.json5`. Reports are written to the gitignored `var/infection/` (text, HTML, and a summary log).

**On-demand only.** Infection is deliberately absent from `mise run check`, the git hooks, and CI — it is a periodic rigor check, not a gate. A run takes around 3 minutes. The task targets the **Unit suite**: Feature and Integration are slow and DB/HTTP-bound, and the unit-tested domain logic (entities, enums, value objects) is the meaningful mutation target.

The baseline is **~82-83% MSI** (around 915 of ~1100 mutants killed, at 100% mutation code coverage). Both the score and the mutant count drift a little between runs, since the suite runs in a random order and which mutants count as covered shifts with it. `minMsi` / `minCoveredMsi` are pinned at **80** in `infection.json5`, under the baseline so an honest run never spuriously fails; ratchet them up as the suite improves.

That leaves 2-3 points of margin against roughly a point of run-to-run variance — deliberately tighter than the 75 it replaced. If the task starts failing without a real regression behind it, the threshold is the first thing to suspect, and lowering it is a legitimate answer.

Four non-default knobs are baked into the `mise run infection` task, each needed for a green run on this image:

- **`XDEBUG_MODE=coverage`** — the image ships Xdebug, not pcov; without a coverage driver Infection aborts.
- **`php -d memory_limit=-1`** — mutation analysis itself succeeds, but the post-run temp-file cleanup walks thousands of files through Symfony Finder and OOMs on the 128M CLI default.
- **`--initial-tests-php-options='-d memory_limit=-1'`** — the same ceiling, for the PHPUnit child process Infection spawns. The parent's `-d` does not reach it, so the child takes php.ini's 128M and the initial run dies partway through the Unit suite under coverage. The two are separate settings: raising one does nothing for the other.
- **`--threads=4`** — `--threads=max` exhausts the container file-descriptor limit under coverage and dies mid-run with "Too many open files"; 4 is the stable ceiling.

Infection does not require `phpunit/phpunit` directly - its adapter detects the version at
runtime - but it does cap PHPUnit indirectly, and 0.33 currently holds it at 13.1.x. Infection
0.33 accepts `sebastian/diff` only up to `^8.0`, which caps `sebastian/comparator` at 8.2.x,
which caps PHPUnit at 13.1.x, because PHPUnit 13.2 requires `sebastian/diff ^9.0`. Infection
0.34 widens the constraint to include `^9.0` and releases the chain, so PHPUnit cannot advance
past 13.1 until Infection does.

## Test Output

Tests must produce clean output. `phpunit.dist.xml` is configured with:

- `failOnDeprecation="true"`
- `failOnNotice="true"`
- `failOnWarning="true"`

If expected log output includes errors, those must be captured and asserted.
