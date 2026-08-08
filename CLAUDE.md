# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony 8.1 application for managing subscriptions with payment tracking and event history. Built with PHP 8.5+, it uses Doctrine ORM for data persistence and follows strict type safety and code quality standards.

The app runs inside Docker via FrankenPHP. Developer tooling (PHPStan, PHPUnit, CS Fixer, Rector) executes **inside the `php` container** via the `./bin/dc exec -T php` wrapper — `mise` tasks delegate to it. The docs site builds in its own Node container (`docs/compose.yaml`); only `lint:php` and the `docs:deploy` rsync run directly on the host.

## Local routing (Lolly)

This app runs behind [Lolly](https://code.dev88.work/dev88/lolly), the shared local
dev proxy. The `bin/dc` wrapper auto-detects whether Lolly is running and picks the
mode - there's no flag to set:

- `bin/dc up -d` - start (shared mode if Lolly is up, solo if not).
- `bin/dc down` - stop.

URLs:
- Shared (Lolly running): `https://obol.lolly.localhost` - clean, browser-trusted.
- Solo (Lolly stopped): `http://localhost:8080` on loopback - plain HTTP for quick
  "is it alive" checks. Browser-trusted TLS is Lolly's job.

### Worktrees (read before creating one)

Every git worktree of this app MUST get its own `.env.local` with a UNIQUE
`SERVER_NAME` and `COMPOSE_PROJECT_NAME`:

    SERVER_NAME=obol-<branch>.lolly.localhost
    COMPOSE_PROJECT_NAME=obol-<branch>

The Traefik router is named after `COMPOSE_PROJECT_NAME`. Two worktrees that share
it share a router and silently collide - one shadows the other, and which stack you
reach becomes a coin flip. Setting only `SERVER_NAME` is just as broken (two
`Host()` rules under one router name). Set both.

Full contract and how routing works: `~/Projects/lolly/docs/agents/integrate-an-app.md`.

## Commands

### Stack control
```bash
mise run up                            # start the stack
mise run down                          # stop it
mise run dce -- php bin/console <cmd>  # arbitrary Symfony command
```

### Testing
```bash
mise run test                          # all tests (compact)
mise run test:v                        # all tests (verbose)
mise run test -- --testsuite=Unit      # specific suite
mise run test -- tests/Unit/SomeTest.php
mise run test -- --filter="subscription"
mise run coverage                      # tests + coverage (min 70%)
mise run coverage:report               # HTML report in var/coverage/
mise run infection                     # mutation testing (Unit suite; on-demand, not in check/CI)
```

Infection (mutation testing) is **on-demand only** - never part of `mise run check`, the git hooks, or CI. It targets the Unit suite; baseline is ~79-81% MSI with the threshold pinned just under in `infection.json5`. Three stack-specific knobs (Xdebug coverage driver, unbounded CLI memory, `--threads=4`) are baked into the task. See `docs/src/content/docs/development/testing.md` (Mutation Testing).

### Code Quality
```bash
mise run sa             # PHPStan static analysis (level 9)
mise run cs             # PHP CS Fixer (fix)
mise run cs:check       # PHP CS Fixer (check only)
mise run cs:twig        # Twig CS Fixer (fix)
mise run rector         # Rector
mise run lint:php       # syntax check on changed files (host-side)
mise run check          # sa + test + cs + cs:twig + the JS toolchain in sequence
```

### JS toolchain (dev-only, host-side via npm; run `npm ci` once after pulling)
```bash
mise run js:cs          # Biome: code style + lint (fix)
mise run js:cs:check    # Biome: check only
mise run js:sa          # tsc --checkJs static analysis
mise run js:test        # Vitest unit tests (Stimulus controllers)
```
Mirrors the PHP sa/cs/test trio for `assets/`; nothing is bundled or shipped (AssetMapper + importmap stay the runtime). See `docs/src/content/docs/frontend.md`.

### Database / fixtures
```bash
mise run seed                                  # load fixtures
mise run seed:clear                            # drop + migrate (no fixtures)
mise run db:roles                              # (re)provision the database roles on an existing cluster
mise run dce -- php bin/console doctrine:migrations:migrate
```

**Two database roles, two connections** (`reference/adr/0030`). The application runs entirely on the
`default` connection (`DATABASE_URL`), whose role can read and write rows but **cannot create, alter
or drop anything** - Doctrine, the session handler and the cache pool all share it. Schema changes go
through the `migrations` connection (`MIGRATION_DATABASE_URL`), which `doctrine_migrations.yaml`
selects automatically, so no migration command needs a flag. Two consequences worth knowing before
you write code:

- **Anything Doctrine-backed added later needs `auto_setup: false` and a migration.** A transport or
  cache adapter that creates its own table fails on the request path, in production.
- **Database-level commands need `--connection=migrations`** (`doctrine:database:drop`, `create`).
  `TRUNCATE` is not available to the application at all; delete in foreign-key order instead.
- **Generate migrations with `mise run migration:diff`**, never a bare `doctrine:migrations:diff`.
  Symfony's schema listeners create a probe table on the application's connection to build the
  mapping-derived schema, so the whole command has to run as the owner; the task does that. `migrate`,
  `up-to-date` and `status` need nothing special.

Fixtures seed a single account, `founder@example.com` (primary, verified).

### Local login (dev)

Auth is passwordless (magic link), and dev sets `MAILER_DSN=null://null`, so no link is
ever delivered locally - there is nothing to type on the login form. Sign in with the
non-prod bypass instead (route registered only outside production):

```
https://obol.lolly.localhost/_test/login-as/founder@example.com
```

It authenticates the session directly and redirects to `/`; go to `/app` for the
dashboard. Route: `src/Controller/Test/TestLoginAsController.php` (`GET /_test/login-as/{email}`).
Alternatives if you want the real magic-link flow: point `MAILER_DSN` at a catcher
(e.g. Mailpit) in `.env.local`, or read the link from the Symfony profiler's Mailer panel.

### AI Mate (dev-only MCP server)
```bash
mise run mate            # start the Mate MCP server (stdio) in the php container
mise run mate:tools      # list the MCP tools Mate exposes (diagnostic)
mise run mate:discover   # re-scan vendor after adding/removing a Mate extension
```
[Symfony AI Mate](https://symfony.com/doc/current/ai/components/mate.html) exposes live-app
introspection + dev drivers (PHPUnit, PHPStan, database, logs, container, profiler, composer)
to AI assistants as `mcp__mate__*` tools. It runs **inside the `php` container** and Claude Code
auto-launches it via the committed `.mcp.json`; the stack must be up. Dev-only - never shipped to
prod. Full reference: `docs/src/content/docs/mate.md`.

### Documentation

The developer docs are an Astro Starlight site under `docs/`, built in a Dockerized pnpm
container (nothing on the host) and published to docs.dev88.work/obol via Ook.

```bash
mise run docs:install   # one-time after clone (installs deps in the docs container)
mise run docs:dev       # hot-reload dev server at http://localhost:4321/obol/
mise run docs:build     # build to docs/dist/ (runs the links validator)
mise run docs:check     # astro check (schema + TypeScript)
mise run docs:deploy    # build + rsync to hex:/srv/docs/obol/
```

Full developer documentation is the Starlight site under `docs/src/content/docs/`. Key pages:
- `docs/src/content/docs/architecture/` — domain model, CQRS, controllers, forms/DTOs
- `docs/src/content/docs/development/` — standards, testing, git hooks, mise tasks
- `docs/src/content/docs/deployment.md` — Docker, compose setup, environment vars
- `docs/src/content/docs/ci-cd.md` — Gitea Actions workflow
- `docs/src/content/docs/operations/updates.md` — deploying new versions, migrations

### Gitea Integration
**IMPORTANT**: This project uses Gitea for issue tracking and pull requests, NOT GitHub.

Do all tracker work - issues, comments, labels, pull requests - through the **gitea MCP**
(`mcp__gitea__*` tools), acting as your own user. Do **not** use the `tea` or `gh` CLIs:
`gh` targets GitHub, and `tea` runs under the maintainer's account, which misattributes
agent work to them. If the MCP can't do something, ask a human to do it by hand.

## Git Workflow for Issues

Every change follows: issue -> branch -> tests -> code -> commit -> PR -> merge -> close.

### 1. Issue first
There must be a tracker issue before code. If none exists, create one (via the gitea MCP)
with a clear description and acceptance criteria.

### 2. Branch
Feature branches come off `main`, named `<type>/<issue>-slug` to match the work
(`feat/265-responsive-design`, `chore/433-workflow-docs`, `fix/434-viewport`). Never commit
to `main` directly - the pre-commit hook blocks it.
```bash
git checkout main
git pull origin main
git checkout -b feat/<issue>-slug
```

### 3. Work on the branch
- Write a failing test first (TDD), then implement.
- Commit frequently with Conventional Commits messages; reference the issue with a `refs:`
  footer, never in the subject.
- Keep it green with `mise run check` (sa + tests + cs + twig-cs + the JS toolchain).

### 4. Push and open a PR
Push the branch and open the PR through the gitea MCP, base `main`. Put `Closes #NN` in the
PR **body** (not the title) to auto-close the issue on merge.

### 5. Review and merge
Once approved, squash-merge to `main` (the repo default) and delete the feature branch.

### 6. After merge
Confirm the issue auto-closed, then sync: `git checkout main && git pull`.

## Git Hooks

**Requires Git 2.24+** (for `pre-merge-commit` hook support).

Hooks are plain shell scripts in `.githooks/`, activated via `core.hooksPath`. The
`install-hooks` Composer script wires them on every `composer install`/`composer update`
(`git config --local core.hooksPath .githooks`, guarded to no-op when there is no git
directory). Nothing is copied into `.git/hooks/`. See `docs/src/content/docs/development/git-hooks.md`.

| Hook | Trigger | What Runs |
|------|---------|-----------|
| `pre-commit` | Commit to `main` | **BLOCKED** - use a feature branch |
| `pre-commit` | Commit to branch | Linters (`php -l`, cs-fixer, twig-cs-fixer) + JS lint/types (Biome, `tsc`) |
| `pre-merge-commit` | Any merge | Linters + PHPStan + Tests + JS (Biome, `tsc`, Vitest) |
| `pre-push` | Push (any branch) | Linters + PHPStan + Tests + JS (Biome, `tsc`, Vitest) |

Every hook runs its full check set and reports all failures at once (no fail-fast), so one run surfaces everything instead of a fix-and-rerun loop.

To wire hooks manually: `git config --local core.hooksPath .githooks`

## Architecture

### Domain Model

The core domain revolves around subscription management with the following entities:

- **Subscription**: Main entity tracking recurring payments. Uses PHP 8.4's `public private(set)` property syntax for immutability. All state changes create `SubscriptionEvent` records for audit history.
- **Payment**: Records individual payment transactions linked to subscriptions.
- **SubscriptionEvent**: Audit trail for subscription changes (Update, CostChange, Archive, Unarchive).
- **Category**: Organizes subscriptions into groups.

### Key Patterns

1. **Event Sourcing for Audit**: The `Subscription` entity records all changes as `SubscriptionEvent` entries. When updating a subscription, events are created for both general updates and cost changes separately.

2. **Immutable Properties**: Entities use `public private(set)` properties (PHP 8.4+) to prevent external modification while allowing read access. Use dedicated methods like `update()`, `archive()`, `recordPayment()` to modify state.

3. **ULID Identifiers**: All entities use Symfony ULIDs as primary keys instead of auto-incrementing integers.

4. **Doctrine Repositories**: Standard Doctrine repository pattern. Repositories extend `ServiceEntityRepository`.

5. **Symfony Scheduler**: The `Schedule` class (implements `ScheduleProviderInterface`) is used for recurring tasks. It's stateful and processes only the last missed run.

6. **CQRS via Messenger with data access in the handler layer**: Reads go through the query bus, writes through the command bus. Data access (repositories, `EntityManager`) is confined to the handler layer (`App\Message` - command handlers, query runners, the scheduler handler); callers (controllers, console commands, services) reach data only through the buses. Messages carry `Ulid` value objects, never Doctrine entities or stringified ids; the handler resolves the `Ulid`. Enforced by an architecture test (`tests/Arch/ArchTest.php`). See `reference/adr/0006` and `reference/adr/0007`.

### Directory Structure

- `src/Entity/`: Doctrine entities with domain logic
- `src/Repository/`: Doctrine repositories
- `src/Enum/`: PHP 8.1+ backed enums (PaymentPeriod, PaymentType, SubscriptionEventType)
- `src/Controller/`: Symfony controllers (currently minimal)
- `src/DataFixtures/`: Database fixtures for testing
- `tests/Unit/`: Unit tests
- `tests/Feature/`: Feature/integration tests

### Frontend

Uses Symfony AssetMapper with:
- Hotwired Stimulus for JavaScript
- Hotwired Turbo for navigation
- No build step (importmap-based); no Node.js at runtime or build time
- A dev-only JS toolchain (Biome + Vitest + `tsc --checkJs`) for the Stimulus controllers, mirroring the PHP sa/cs/test trio. Node tooling run at dev/CI time only; nothing is bundled or shipped. See `docs/src/content/docs/frontend.md`.

## Code Quality Standards

This project enforces strict quality standards:

### Objects are always in a valid state

The core design rule in this codebase: **an object must never be representable in an invalid state.** A class owns its invariants; no caller can put it into a state its own rules forbid. This is why the domain uses rich entities with methods like `update()`/`archive()` instead of `setName()`/`setStatus()`.

- **Constructors produce complete, valid objects.** No "construct now, finish populating later." If an object is only valid alongside a related object, the constructor creates it - e.g. `User::__construct()` builds its own primary verified `UserEmail`, so a `User` can never exist without one. Don't rely on a caller (a handler, a factory) to complete construction: the caller can forget, and the invariant becomes a multi-step contract any step can break.
- **Enforce each invariant in exactly one un-bypassable place** - the constructor or a domain method - never split across the object *and* its callers. If validity depends on step A in one file and step B in another, either can be dropped and the object goes invalid.
- **Mutate through intention-revealing domain methods, never setters.** `update()`, `archive()`, `recordPayment()` take a whole coherent change and enforce the invariants for it. A `setX()` lets a caller change one field and skip the related ones, leaving the object inconsistent. Reaching for a setter usually means you're modeling the wrong operation.
- **Asymmetric visibility keeps outside code out.** Properties are `public private(set)` - read freely, written only from within (ADR-0003). External code observes state but can only change it through the entity's own methods.
- **Fail fast at the boundary.** Constructors and domain methods assert their preconditions (beberlei `Assertion`, or throw `\InvalidArgumentException`) and reject bad input rather than storing it. Prefer making an illegal object un-constructable over constructing it and validating afterward.
- **Make illegal states unrepresentable in the type system** where you can: backed enums over magic strings/bools, value objects (`Money`, `Ulid`) over bare primitives, and model "absent" explicitly (a nullable relation) rather than with sentinel values.
- **The database mirrors invariants; it doesn't own them.** DB constraints (NOT NULL, FKs, unique and partial indexes) are a backstop that catches bugs at flush time. Enforce the same rule in the object too, so in-memory instances are honest before they touch the database and failures carry domain meaning instead of surfacing as a raw constraint violation. (E.g. `UserEmail` rejects an unverified primary in its constructor *and* a partial unique index enforces one-primary-per-user.)

### Comments and documentation
- **No issue/PR numbers in code comments or documentation.** Comments and docs (code comments,
  `README.md`, `docs/`, `docs-user/`, `CONTEXT.md`, ADRs, migration descriptions) must be
  self-contained to the application - a reader with only the repo should never hit a reference they
  can't resolve. Gitea issue/PR numbers live outside the codebase, so they don't belong here.
- You **may** reference in-repo decisions, since those travel with the code: an ADR
  (`reference/adr/NNNN`) or an out-of-scope note (`reference/out-of-scope/`).
- This rule covers docs and comments only. Issue/PR descriptions, comments, and commit/PR
  `refs:`/`Closes` footers on Gitea are unaffected - cross-referencing is exactly their job.

### Product voice (user-facing copy)
Obol is a neutral tool, not an agent. In anything the app says to a user - `docs-user/`, UI
strings and translations, form labels, flash messages - never give Obol a will, an opinion, or
a judgment. It **shows**, **displays**, **records**, and **calculates** from the user's
preferences; it does not **expect**, **want**, **think**, **judge**, **remind you that you
should**, or even **observe**. A calculator doesn't observe anything - it is just a tool. Write
in terms of the user and their settings ("how much you should have set aside, according to your
savings preference"), never the app's expectations of them.

Keep it short. A UI string is read at a glance, not studied: state the situation, then what to do,
and stop. Explaining the mechanism behind a rule belongs in `docs-user/`, not in the panel. Two plain
sentences beat one careful paragraph, and a heading that restates its own body is the first thing to
cut.

### Suppressing an analyzer finding
When PHPStan, Rector, CS Fixer, Biome, or Igor flags something, resolve it - either fix the code, or,
where it is genuinely a false positive, suppress it **at the line with the reason written next to
it** (`// @igor-ignore - why`, a targeted `ignoreErrors` entry with a message and a path). Triage
every finding on its own before reaching for any suppression mechanism.

Never make a build green by regenerating a baseline over first-party code. A baseline is an
unreviewable blob: a real bug hidden there is indistinguishable from a false positive, and no one
reads it again. Baselines are for third-party code that cannot be changed - `igor-baseline.json` is
vendor-only for exactly this reason (see `docs/src/content/docs/development/worker-mode.md`), and
every entry still carries a filled-in `reason`.

If the honest answer is "all of these are false positives", say so and show the reasoning. Do not
perform a fix on correct code to make a tool happy.

### Pinning the tools that gate CI
Anything that can fail the build is pinned to an exact version, not a range. A gate that moves on its
own is not a gate, and a tool whose suppressions match on message text goes stale the moment it
rewords a finding - which surfaces as a red build on a commit that changed nothing.

So when a check fails and the diff looks unrelated, check the tool's running version against the
lockfile, and check whether the same failure reproduces on `main`. That settles "did I break this" in
one run, before any code gets investigated.

### Type Coverage
100% type coverage required for:
- Return types
- Parameters
- Properties
- Constants
- Declare statements

### Forbidden Practices
- **No debugging functions**: `dump()`, `dd()`, `var_dump()` - use logger instead
- **No dangerous functions**: `extract()`, `compact()`, `curl_*`, `method_exists()`, `property_exists()`
- **No error suppression**: `@` operator
- **No post-increment/decrement**: Use pre-increment/decrement
- **No empty()**: Write explicit checks
- **No string interpolation**: Use concatenation or sprintf

### Symfony Best Practices
- No `$this->get()` in controllers or commands
- No `AbstractController::__construct()` with dependencies
- Controllers must use dependency injection via method parameters or constructor
- Wire services with `#[Autowire]` attributes on the class, not in `services.yaml` (parameters stay in `services.yaml`; wiring lives on the constructor argument). Migrate existing argument blocks as touched.
- No global constants
- Routes must have names for generation
- No trailing slashes in routes
- No class-level route attributes (use method-level only)
- Form types must end with `Type` suffix
- Listeners must implement a contract interface

### Doctrine Best Practices
- No `getRepository()` calls outside services
- Use repository service injection

### Testing
- Tests use PHPUnit (test classes namespaced under `App\Tests\`)
- Zenstruck Foundry for fixtures
- Architecture tests in `tests/Arch/` enforce structural rules via reflection/source scans; the "no debug functions" rule lives in PHPStan (`ForbiddenFuncCallRule`)
- Test suites: Unit, Feature, Integration, Arch
- `tests/` has its own relaxed PHPStan profile (`mise run sa:tests`, `phpstan-tests.neon`), separate from the strict `src/` profile (`mise run sa`)

## Code Coverage

Coverage is enforced at **70% minimum** in both CI and `mise run coverage`. PHPUnit has no native threshold flag (Pest's `--min` did), so `bin/coverage-min.php` reads the Clover report and fails below the minimum. The threshold is intentionally set conservatively and should be manually ratcheted up over time:

1. Run `mise run coverage` to see the current coverage percentage
2. If coverage is consistently above the threshold (e.g., 90% actual vs 70% minimum), bump the minimum passed to `bin/coverage-min.php` in:
   - `mise.toml` (`tasks.coverage`)
   - `.gitea/workflows/ci.yml` (PHPUnit step)
3. Coverage reports can be generated locally with `mise run coverage:report` (output in `var/coverage/`)

## Notes

- PHP 8.5+ features are actively used (e.g., `public private(set)`, property hooks syntax)
- All code must pass PHPStan level 9 with strict rules
- Uses Rector for automated refactoring
- Symfony Flex manages bundles and recipes
- Multi-stage FrankenPHP Dockerfile; six compose files, each named for its place in the chain. `compose.yaml` is the base; `compose.dev.yaml` is dev-only, with `compose.dev.solo.yaml` / `compose.dev.shared.yaml` handling loopback HTTP vs Lolly routing (`bin/dc` auto-picks); `compose.prod.yaml` is the prod overlay and `compose.prod.tunnel.yaml` adds the Cloudflare connector on top of it (`bin/dc-prod` pins the chain). Nothing is auto-loaded - there is no `compose.override.yaml`, so every overlay must be named, and a bare `docker compose` gets the base stack only
