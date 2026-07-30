---
title: "Symfony AI Mate (dev-only MCP server)"
---

[Symfony AI Mate](https://symfony.com/doc/current/ai/components/mate.html) is a
development-only [MCP](https://modelcontextprotocol.io/) server that exposes live-app
introspection and dev drivers (tests, static analysis, logs, container,
profiler, composer) to an AI assistant, so AI-assisted work on Obol is grounded in the
running app instead of guesswork. It is never shipped to production.

## How it runs: inside the `php` container

Mate runs *inside* the `php` container, not on the host. Obol has no host PHP in its
workflow - every PHP tool runs via `./bin/dc exec -T php …`. Several extensions boot the
Symfony kernel or read the log files, so they must run where the app and its environment
live. Running in-container also means the PHPUnit /
PHPStan / Composer extensions invoke their binaries directly, with no `docker compose exec`
wrapper to configure.

> The stack must be up (`mise run up`) for the MCP server to start. When it is down, the
> AI client reports the server as unavailable.

## Registering it with the AI client

Claude Code auto-discovers the project MCP manifest at the repo root, `.mcp.json`,
which launches the server through the dockerized command:

```json
{ "mcpServers": { "mate": { "command": "./bin/dc",
    "args": ["exec", "-T", "php", "vendor/bin/mate", "serve", "--force-keep-alive"] } } }
```

The MCP transport is stdio (JSON-RPC on stdout). `./bin/dc` is safe here because its Lolly
auto-detection is redirected to `/dev/null` before it `exec`s `docker compose`, so stdout
carries only the protocol stream.

The Mate tools surface to Claude Code as `mcp__mate__*`. To skip per-tool approval prompts,
add `mcp__mate__*` to `permissions.allow` in `.claude/settings.json`
(left to each developer's discretion - it is not committed by default).

### Manual use

```bash
mise run mate            # start the MCP server (stdio) by hand
mise run mate:tools      # list the MCP tools Mate exposes (diagnostic)
mise run mate:discover   # re-scan vendor after adding/removing a Mate extension
```

To exercise a single tool without the full MCP handshake:

```bash
./bin/dc exec -T php vendor/bin/mate mcp:tools:call phpunit-run '{"filter":"CurrencyTest"}'
```

## Extensions and tools

Six extensions are enabled in `mate/extensions.php`, giving 19
tools. Prefer these over the equivalent raw CLI - they return compact, structured output.

| Extension (package) | Tools | Status |
| --- | --- | --- |
| Core (`symfony/ai-mate`) | `server-info` | works |
| Symfony bridge (`symfony/ai-symfony-mate-extension`) | `symfony-services`, `symfony-service-detail`, `symfony-profiler-list`, `symfony-profiler-get` | works (profiler tools need collected profiles - Obol runs `web-profiler-bundle` in dev/test) |
| Monolog (`symfony/ai-monolog-mate-extension`) | `monolog-search`, `monolog-context-search`, `monolog-tail`, `monolog-list-files`, `monolog-list-channels` | works |
| Composer (`matesofmate/composer-extension`) | `composer-install`, `composer-require`, `composer-remove`, `composer-update`, `composer-explain` | tools work; the `composer://config` resource fails to register (see limitations) |
| PHPStan (`matesofmate/phpstan-extension`) | `phpstan-analyse`, `phpstan-clear-cache` | works (see PHPStan note) |
| PHPUnit (`matesofmate/phpunit-extension`) | `phpunit-run`, `phpunit-list-tests` | works |

Database introspection tools (`database-query`, `database-schema`) are not available: they
came from `ineersa/database-extension`, which was dropped because its `symfony/ai-mate ^0.8`
cap held the whole Mate stack back. Inspect the schema and run queries with the raw
`./bin/dc exec -T php bin/console dbal:run-sql` / `doctrine:query:sql` instead.

### Configuration notes

- *PHPUnit / PHPStan / Composer extensions need no `custom_command`.* Because Mate runs
  in-container, they invoke `vendor/bin/*` directly.
- *PHPStan memory.* PHPStan analysing this Symfony/Doctrine app exhausts the container's
  128M CLI `memory_limit` (the same reason `mise run sa` runs with `--memory-limit=4G`).
  Mate's PHPStan extension takes a command prefix, so `mate/config.php`
  sets `matesofmate_phpstan.custom_command` to `php -d memory_limit=4G vendor/bin/phpstan`.
- *Monolog reads `var/log`.* No extra configuration was needed: Obol's dev logging already
  writes rotating files to `var/log` (`config/packages/monolog.yaml`), so the Monolog tools
  have logs to read out of the box (`var/` is gitignored).
- *`helgesverre/toon` is kept for compact output.* Mate suggests it for "reduced token
  consumption." The MatesOfMate extensions use it for compact tool output when it is present,
  so it stays in `require-dev` even though nothing hard-requires it since the database
  extension (which called `Toon::encode()` directly) was dropped.

### Known limitations

- *`composer://config` resource.* The Composer extension logs `Failed to process MCP
  attribute on …\ConfigResource::getConfiguration` during discovery. It is a non-fatal
  upstream issue affecting only that one resource - the five `composer-*` tools register and
  work, and the message goes to stderr, so it does not corrupt the MCP stream.

(The profiler tools have data, since Obol runs the web profiler in dev/test.)

## Staying dev-only

Production never sees Mate:

- All Mate packages (and `helgesverre/toon`) are in `require-dev`, so the prod builder's
  `composer install --no-dev` excludes them.
- `mate/`, `.mcp.json`, and `*.md` (covering `AGENTS.md`) are in `.dockerignore`, so the Mate
  source and config never enter the prod image.
- `config/packages/http_discovery.yaml` comes from
  the `php-http/discovery` Flex recipe (pulled in transitively by Mate) and is scoped to
  `when@dev` + `when@test`. The package is dev-only, so loading those PSR-17 service
  definitions in prod would fail container compilation on the missing
  `Http\Discovery\Psr17Factory` class. Verified with a `--target frankenphp_prod` image build.

The harmless `App\Mate\` autoloader entry and `extra.ai-mate` block stay in `composer.json`;
without the Mate binary (absent in prod) they are inert.

## Adding or removing an extension

The `symfony/ai-mate-composer-plugin` (which would auto-run discovery on every `composer
install`) is *disallowed* in `composer.json` so that `mate/extensions.php`
stays the committed source of truth and is not silently regenerated in CI. After `composer
require --dev`-ing or removing a Mate extension, run `mise run mate:discover` and review the
diff to `mate/extensions.php` (and the Mate-managed `AGENTS.md` / `mate/AGENT_INSTRUCTIONS.md`
blocks).

---

## Changelog

- 2026-07-30 - Configuration files are named rather than linked to the repository host, so the page
  does not assume where the code is hosted.
- 2026-06-14 - Initial Mate integration.
