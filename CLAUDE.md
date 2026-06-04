# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony 8.0 application for managing subscriptions with payment tracking and event history. Built with PHP 8.5+, it uses Doctrine ORM for data persistence and follows strict type safety and code quality standards.

The app runs inside Docker via FrankenPHP. Developer tooling (PHPStan, Pest, CS Fixer, Rector) executes **inside the `php` container** via the `./bin/dc exec -T php` wrapper — `mise` tasks delegate to it. Only `docs:*` and `lint:php` run on the host.

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
```

### Code Quality
```bash
mise run sa             # PHPStan static analysis (level 9)
mise run cs             # PHP CS Fixer (fix)
mise run cs:check       # PHP CS Fixer (check only)
mise run cs:twig        # Twig CS Fixer (fix)
mise run rector         # Rector
mise run lint:php       # syntax check on changed files (host-side)
mise run check          # sa + test + cs + cs:twig in sequence
```

### Database / fixtures
```bash
mise run seed                                  # load fixtures
mise run seed:clear                            # drop + migrate (no fixtures)
mise run dce -- php bin/console doctrine:migrations:migrate
```

### Documentation
```bash
# Serve docs locally (requires mkdocs-material: pipx install mkdocs-material)
mise run docs:serve

# Build docs site (output to site/)
mise run docs:build

# Deploy to docs.dev88.work/obol
mise run docs:deploy
```

Full developer documentation is in `docs/`. Key pages:
- `docs/architecture/` — domain model, CQRS, controllers, forms/DTOs
- `docs/development/` — standards, testing, git hooks, mise tasks
- `docs/deployment.md` — Docker, compose setup, environment vars
- `docs/ci-cd.md` — Gitea Actions workflow
- `docs/operations/updates.md` — deploying new versions, migrations

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
directory). Nothing is copied into `.git/hooks/`. See `docs/development/git-hooks.md`.

| Hook | Trigger | What Runs |
|------|---------|-----------|
| `pre-commit` | Commit to `main` | **BLOCKED** - use a feature branch |
| `pre-commit` | Commit to branch | Linters (`php -l`, cs-fixer, twig-cs-fixer) |
| `pre-merge-commit` | Any merge | Linters + PHPStan + Tests |
| `pre-push` | Push branch | Linters |
| `pre-push` | Push to `main` | Linters + PHPStan + Tests |

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
- No build step (importmap-based)

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
- Tests use Pest PHP (runs on top of PHPUnit)
- Zenstruck Foundry for fixtures
- Architecture tests in `tests/Arch/` enforce structural rules
- Test suites: Unit, Feature, Integration

## Code Coverage

Coverage is enforced at **70% minimum** via `--min=70` in both CI and `mise run coverage`. The threshold is intentionally set conservatively and should be manually ratcheted up over time:

1. Run `mise run coverage` to see the current coverage percentage
2. If coverage is consistently above the threshold (e.g., 85% actual vs 70% minimum), bump `--min=N` in:
   - `mise.toml` (`tasks.coverage`)
   - `.gitea/workflows/ci.yml` (Pest step)
3. Coverage reports can be generated locally with `mise run coverage:report` (output in `var/coverage/`)

## Notes

- PHP 8.5+ features are actively used (e.g., `public private(set)`, property hooks syntax)
- All code must pass PHPStan level 9 with strict rules
- Uses Rector for automated refactoring
- Symfony Flex manages bundles and recipes
- Multi-stage FrankenPHP Dockerfile; `compose.yaml` is the base, `compose.override.yaml` is dev-only, `compose.solo.yaml` / `compose.shared.yaml` handle loopback HTTP vs Lolly routing (`bin/dc` auto-picks), `compose.prod.yaml` is the prod overlay
