# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony 7.3 application for managing subscriptions with payment tracking and event history. Built with PHP 8.4+, it uses Doctrine ORM for data persistence and follows strict type safety and code quality standards.

## Commands

### Development
```bash
# Run Symfony console commands
php bin/console <command>

# Start development server
symfony serve
# or
php -S localhost:8000 -t public
```

### Testing
```bash
# Prefer running tests via mise
mise run test

# If needing direct Pest access
php vendor/bin/pest

# Run specific test suite
mise run test --testsuite=Unit
mise run test --testsuite=Feature
mise run test --testsuite=Integration

# Run single test file
mise run test tests/Unit/SomeTest.php

# Run tests matching a filter
mise run test --filter="subscription"
```

### Code Quality
```bash
# PHPStan static analysis (level 9)
mise run sa

# Fix code style with PHP CS Fixer
mise run cs

# Run Rector refactoring
mise run rector

# Fix Twig templates
mise run cs:twig
```

### Database
```bash
# Run migrations
php bin/console doctrine:migrations:migrate

# Load fixtures
php bin/console doctrine:fixtures:load
```

### Mise Tasks
If `mise` is installed, use these shortcuts:
```bash
mise run lint:php       # PHP syntax check on changed files
mise run sa            # Static analysis
mise run test          # Run tests (compact output)
mise run test:v        # Run tests (verbose output)
mise run coverage      # Run tests with coverage (min 70%)
mise run coverage:report  # Generate HTML coverage report in var/coverage/
mise run cs            # Fix code style
mise run cs:twig       # Fix twig code style
mise run rector        # Run Rector
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

Hooks are managed by [Captain Hook](https://github.com/captainhook-git/captainhook) (`captainhook.json`) with one standalone hook for `pre-merge-commit` (stored in `.githooks/`). Both are auto-installed on `composer install`.

| Hook | Trigger | What Runs |
|------|---------|-----------|
| `pre-commit` | Commit to `main` | **BLOCKED** — use a feature branch |
| `pre-commit` | Commit to branch | Linters (`php -l`, cs-fixer, twig-cs-fixer) |
| `pre-merge-commit` | Any merge | Linters + PHPStan + Tests |
| `pre-push` | Push branch | Linters |
| `pre-push` | Push to `main` | Linters + PHPStan + Tests |

To reinstall hooks manually: `vendor/bin/captainhook install --force && composer run install-hooks`

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

- PHP 8.4+ features are actively used (e.g., `public private(set)`, property hooks syntax)
- All code must pass PHPStan level 9 with strict rules
- Uses Rector for automated refactoring
- Symfony Flex manages bundles and recipes
- Docker support available (see compose.yaml)
