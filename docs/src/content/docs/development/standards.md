---
title: "Code Quality Standards"
---

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
- Inject services through the **constructor**, not controller method
  parameters. The `Request` (and route arguments like the `Ulid`) stay as
  method parameters; everything else is constructor-injected. Rector's
  `ControllerMethodInjectionToConstructorRector` enforces this. The
  `AbstractBaseController` bus wiring uses `#[Required]` setter injection, so
  child constructors coexist with it.
- **Wire services with `#[Autowire]` attributes on the class, not in
  `services.yaml`.** Parameters still live in `services.yaml` under `parameters:`
  (that is where machine/deploy configuration belongs), but a service that needs
  one binds it on the constructor argument -
  `#[Autowire(param: 'app.uploads_directory')] private string $targetDirectory`.
  Keeping the wiring on the class makes a service's dependencies legible from the
  class itself instead of a separate YAML block - less "magic." Migrate existing
  `services.yaml` argument blocks to attributes as you touch them.
- Form types must end with `Type` suffix
- No class-level `#[Route]` attributes (method-level only)
- No trailing slashes in routes
- All routes must have names
- Listeners must implement a contract interface
- **Message handlers do not call `EntityManager::flush()`.** Every bus
  (`command.bus`, `query.bus`, `event.bus`) carries the `doctrine_transaction`
  middleware, which opens a transaction around the handler and commits it on
  return. A handler `persist()`s or `remove()`s and returns; the middleware
  owns the transaction boundary. A flush is only warranted mid-handler when the
  handler must read back a DB-generated value before doing more work — rare,
  since entities mint their own ULIDs. (Retrofitting the older handlers that
  still flush redundantly is tracked in #148.)

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

Rector (`mise run rector`) performs automated refactoring. The config in
`rector.php` carries its own rationale comments; the enabled sets are:

- `deadCode`, `codeQuality`, `codingStyle`
- `typeDeclarations`, `privatization`, `instanceOf`, `earlyReturn`
- `phpunitCodeQuality`, `doctrineCodeQuality`
- `symfonyCodeQuality`, `symfonyConfigs`
- Composer-based sets: `twig`, `doctrine`, `phpunit`, `symfony`

Versions track automatically: `withPhpSets()` and `withComposerBased()`
follow the installed PHP and Symfony versions, so there are no manual
version sets to bump or dedupe. In Rector 2.x the Symfony/Doctrine/PHPUnit/
Twig rules ship inside `rector/rector` itself — there are no separate
`rector/rector-*` packages.

A few rules are skipped or tuned deliberately (see `withSkip()` /
`withConfiguredRule()` in `rector.php`): `FunctionFirstClassCallableRector`
is skipped because its `'fn'` → `fn(...)` rewrites read poorly here, and
`EncapsedStringsToSprintfRector` runs with `always: false` so simple cases
collapse to concatenation and `sprintf()` is only used when literal text is
interleaved.

Rector is not always fixed-point in one pass — re-run `mise run rector`
until it reports "Rector is done!". CI runs `rector --dry-run` non-blocking;
keep it clean.

## Comment Guidelines

- Comments must be evergreen — no temporal references like "recently refactored" or "moved from"
- Do not name things "new", "enhanced", or "improved"
- Do not remove existing comments unless they are provably false
- Avoid unnecessary comments — let the code speak for itself
