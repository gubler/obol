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

# If needing direct PHPUnit access
php vendor/bin/phpunit

# Run specific test suite
mise run test --testsuite=Unit
mise run test --testsuite=Feature

# Run single test file
mise run test tests/Unit/SomeTest.php
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
mise run sa        # Static analysis
mise run test      # Run tests
mise run cs        # Fix code style
mise run cs:twig   # Fix twig code style
mise run rector    # Run Rector
```

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
- Tests use PHPUnit
- Zenstruck Foundry for fixtures
- Both unit and feature test suites available

## Notes

- PHP 8.4+ features are actively used (e.g., `public private(set)`, property hooks syntax)
- All code must pass PHPStan level 9 with strict rules
- Uses Rector for automated refactoring
- Symfony Flex manages bundles and recipes
- Docker support available (see compose.yaml)
