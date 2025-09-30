# Tech Stack

## Backend

### Core Framework
- **Symfony 7.3** - Full-stack PHP framework
  - symfony/framework-bundle
  - symfony/console
  - symfony/scheduler (for recurring tasks)
  - symfony/validator
  - symfony/form

### Database & ORM
- **SQLite** - Lightweight file-based database (perfect for personal use)
- **Doctrine ORM 3.5+** - Database abstraction and entity management
  - doctrine/orm
  - doctrine/dbal
  - doctrine/doctrine-bundle
  - doctrine/doctrine-migrations-bundle
  - doctrine/doctrine-fixtures-bundle
- **ULIDs** - Universally Unique Lexicographically Sortable Identifiers
  - symfony/uid

### PHP
- **PHP 8.4+** - Latest PHP with modern features
  - `public private(set)` asymmetric visibility
  - Strict types (`declare(strict_types=1)`)
  - Backed enums
  - Constructor property promotion
  - Named arguments

### Testing
- **Pest PHP 4.0** - Testing framework with expressive syntax
- **PHPUnit 12.3+** - Underlying test runner
- **Zenstruck Foundry 2.6+** - Fixture factories

**CRITICAL DEVELOPMENT PRACTICE:**
- **Test-Driven Development (TDD) is MANDATORY**
- All code must have tests written FIRST before implementation
- No feature implementation without corresponding tests in place
- Test suites: Unit tests (tests/Unit/) and Feature tests (tests/Feature/)

### Code Quality & Static Analysis
- **PHPStan 2.1+** - Static analysis at level 9
  - phpstan/phpstan-symfony
  - phpstan/phpstan-doctrine
  - phpstan/phpstan-phpunit
  - phpstan/phpstan-deprecation-rules
  - spaze/phpstan-disallowed-calls (security rules)
  - symplify/phpstan-rules (additional strict rules)
  - tomasvotruba/type-coverage (100% type coverage enforcement)

- **PHP CS Fixer 3.86+** - Code style automation
  - @PhpCsFixer ruleset
  - @Symfony ruleset with risky rules

- **Rector 2.1+** - Automated refactoring
  - Dead code elimination
  - Type declarations
  - Symfony-specific refactoring
  - Doctrine optimizations

- **Twig CS Fixer 3.9+** - Template linting

### Dependencies
- **dragonmantank/cron-expression 3.4** - CRON expression parsing (for scheduling)
- **symfony/monolog-bundle 3.10** - Logging
- **symfony/mailer 7.3** - Email capabilities (for future notifications)
- **symfony/notifier 7.3** - Notification system

## Frontend

### Asset Management
- **Symfony AssetMapper** - No-build asset pipeline
  - importmap.php configuration
  - Native ES modules

### JavaScript Framework
- **Hotwired Stimulus 3.2** - Modest JavaScript framework
  - symfony/stimulus-bundle
- **Hotwired Turbo 7.3** - SPA-like navigation without build step
  - symfony/ux-turbo

### Planned Frontend Tools
- **Biome JS** - Fast linter/formatter for JavaScript/TypeScript
  - To be configured when frontend development begins
  - Replaces ESLint + Prettier

### UI Components
- **Symfony UX Components**
  - symfony/ux-twig-component (server-side components)
  - symfony/ux-icons (icon system)

### CSS
- **Tailwind 4.1**

## Development Tools

### Task Runner
- **Mise** - Task runner and environment manager
  - mise.toml for common tasks (sa, test, cs, rector)

### Development Environment
- **Docker** - Container orchestration
  - compose.yaml for local development
  - compose.override.yaml for customization

### Version Control
- **Git** - Source control
  - **Conventional Commits** for commit message format

## Infrastructure

### Database
- **SQLite** - File-based relational database
  - No separate database server needed
  - Perfect for personal use application
  - Configured via Doctrine

### Web Server
- **Laravel Herd** or **Symfony CLI** (development)
- Production: TBD (Likely Docker with FrankenPHP)

## Configuration Management

- **.env files** - Environment-specific configuration
  - .env (defaults)
  - .env.dev
  - .env.test
- **symfony/dotenv** - Environment variable parsing
- **symfony/flex** - Dependency management and recipes

## Quality Standards

### Development Process
- **TEST-DRIVEN DEVELOPMENT (TDD) REQUIRED**
  - Write tests BEFORE writing implementation code
  - No exceptions - all features must be test-first
  - Tests define the contract and expected behavior

### Enforced Rules
- 100% type coverage (parameters, returns, properties, constants)
- PHPStan level 9 (strictest analysis)
- No forbidden functions: dump(), dd(), var_dump(), extract(), etc.
- No error suppression (@)
- No global constants
- Symfony best practices (no $this->get() in controllers, DI everywhere)
- Doctrine best practices (no getRepository() outside services)

### Code Style
- @PhpCsFixer + @Symfony rulesets
- Short array syntax
- Strict parameter type hints
- Named arguments for clarity
- Parallel execution with PHP CS Fixer

---

**Note**: No API framework planned. No user authentication needed (personal use only).
