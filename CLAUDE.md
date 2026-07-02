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
mise run dce -- php bin/console doctrine:migrations:migrate
```

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
**IMPORTANT**: This project uses Gitea for issue tracking, NOT GitHub.

```bash
# List issues
tea issues list

# View issue details
tea issues show <issue-number>

# Create new issue
tea issues create

# Add comment to issue
tea comment <issue-number> "Comment text..."

# Close issue
tea issues close <issue-number>
```

**NEVER use `gh` CLI** - it's for GitHub only. Always use `tea` for Gitea operations.

## Git Workflow for Issues

Follow this workflow when working on issues:

### 1. Create Issue
Create the issue in Gitea (via `tea issues create` or web UI) with clear description and acceptance criteria.

### 2. Create Branch
```bash
git checkout main
git pull origin main
git checkout -b issue-##-brief-description
```

### 3. Work on Branch
- Write failing tests first (TDD)
- Implement the solution
- Commit frequently with conventional commit messages
- Ensure all tests pass (`mise run test`)
- Ensure static analysis passes (`mise run sa`)
- Ensure code style passes (`mise run cs`)

### 4. Push and Create PR
```bash
git push -u origin issue-##-brief-description
tea pulls create --title "Title" --description "Description" --base main
```

### 5. Review and Merge
- Wait for review/approval (or self-review if authorized)
- Address any feedback
- Merge PR to main (via Gitea UI or CLI)
- Pull latest main: `git checkout main && git pull`

### 6. Close Issue with Reference
```bash
# Add closing comment with commit hash from merged PR
tea comment <issue-number> "Closed by commit <hash>

Summary of changes...
- What was implemented
- Files created/updated
- Test results"

# Close the issue
tea issues close <issue-number>

# Delete feature branch (optional)
git branch -d issue-##-brief-description
git push origin --delete issue-##-brief-description
```

**Note**: When working on larger feature branches (like the current `subscriptions` branch), you may work directly on that branch with multiple issues before creating a final PR to main.

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
- Multi-stage FrankenPHP Dockerfile; `compose.yaml` is the base, `compose.override.yaml` is dev-only, `compose.solo.yaml` / `compose.shared.yaml` handle loopback HTTP vs Lolly routing (`bin/dc` auto-picks), `compose.prod.yaml` is the prod overlay
