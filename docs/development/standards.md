# Code Quality Standards

Obol enforces strict quality standards through PHPStan, PHP CS Fixer, Rector, and architectural tests.

## PHPStan Level 9

Static analysis runs at the maximum level with additional strict rule sets. Configuration is in `phpstan.dist.neon`.

### Type Coverage

100% type coverage is required for:

- Return types
- Parameters
- Properties
- Constants
- Declare statements (`declare(strict_types=1)`)

### Forbidden Functions

These functions are banned via `spaze/phpstan-disallowed-calls`:

| Function | Reason |
|----------|--------|
| `dump()`, `dd()`, `var_dump()` | Use the logger instead |
| `extract()`, `compact()` | Obscure variable scope |
| `curl_*` | Use Symfony HttpClient |
| `method_exists()`, `property_exists()` | Type-unsafe reflection |
| `spl_autoload_register()`, `spl_autoload_unregister()` | Use Composer autoloading |
| `array_walk()` | Use `array_map()` or foreach |

### Forbidden Syntax

| Pattern | Rule |
|---------|------|
| `empty()` | Write explicit checks (e.g., `[] === $var`) |
| `@` error suppression | Handle errors explicitly |
| String interpolation (`"$var"`) | Use concatenation or `sprintf()` |
| Post-increment (`$i++`) | Use pre-increment (`++$i`) |

### Symplify Structural Rules

Enforced via `symplify/phpstan-rules`:

- No extending non-abstract classes
- No global constants
- Exceptions must live in `Exception\` namespace
- Attributes must use named arguments
- Enums must have unique constant values
- No reference parameters
- No multiple classes per file

### Symfony-Specific Rules

- No `AbstractController::__construct()` — use `#[Required]` injection
- No `$this->get()` in controllers or commands
- Form types must end with `Type` suffix
- No class-level `#[Route]` attributes (method-level only)
- No trailing slashes in routes
- All routes must have names
- Listeners must implement a contract interface

## File Comments

All PHP code files must start with two ABOUTME comment lines:

```php
// ABOUTME: Brief description of what this file does.
// ABOUTME: Additional context about purpose or patterns used.
```

The `ABOUTME:` prefix makes them greppable across the codebase.

## Code Style

**PHP CS Fixer** (`mise run cs`) handles formatting automatically. Run it before committing — the pre-commit hook runs it too.

**Twig CS Fixer** (`mise run cs:twig`) handles Twig template formatting.

Both tools auto-fix on commit (pre-commit hook) and check-only in CI.

## Rector

Rector (`mise run rector`) performs automated refactoring. All prepared sets are enabled:

- `deadCode`, `codeQuality`, `codingStyle`
- `typeDeclarations`, `privatization`, `instanceOf`
- `earlyReturn`, `strictBooleans`
- `symfonyCodeQuality`, `symfonyConfigs`
- Composer-based sets: `twig`, `doctrine`, `phpunit`, `symfony`

## Comment Guidelines

- Comments must be evergreen — no temporal references like "recently refactored" or "moved from"
- Do not name things "new", "enhanced", or "improved"
- Do not remove existing comments unless they are provably false
- Avoid unnecessary comments — let the code speak for itself
