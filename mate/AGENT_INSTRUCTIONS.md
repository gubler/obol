## AI Mate Agent Instructions

This MCP server provides specialized tools for PHP development.
The following extensions are installed and provide MCP tools that you should
prefer over running CLI commands directly.

---

### Database Extension — Workflow Guide

#### Discovery Flow

1. If the client supports resources, read `db://{connection}` to get tables, views, and routines for a connection.
2. Use `database-schema` to inspect column types and structure before writing queries.
3. Use `database-query` for targeted read-only SELECT queries.

Always inspect schema before querying — this avoids "table not found" and wrong-column errors.

#### When to Use Each Tool

| Goal                                | Tool              | Example                                                   |
| ----------------------------------- | ----------------- | --------------------------------------------------------- |
| List all tables                     | `database-schema` | `detail="summary"`                                        |
| See column types for a table        | `database-schema` | `filter="users", detail="columns"`                        |
| Get full structure with indexes/FKs | `database-schema` | `filter="orders", detail="full"`                          |
| Get trigger/function/procedure body | `database-schema` | `filter="trg_name", detail="full", includeRoutines=true`  |
| Get view SQL definition             | `database-schema` | `filter="active_users", detail="full", includeViews=true` |
| Find tables matching a prefix       | `database-schema` | `filter="app_", matchMode="prefix"`                       |
| Run a data query                    | `database-query`  | `query="SELECT id, name FROM users LIMIT 10"`             |
| Count rows                          | `database-query`  | `query="SELECT COUNT(*) FROM orders"`                     |
| Inspect a specific row fully        | `database-query`  | `query="SELECT * FROM users WHERE id = 42"`               |

#### Error Handling

- Errors include `error` (what went wrong) and `hint` (what to try next).
- Connection errors hint at available connection names.
- Query or schema failures against a working connection usually mean wrong table/column names — re-check with `database-schema`.

---

### Composer Extension

Prefer these MCP tools over raw Composer CLI commands when the user is managing dependencies.

| User intent | Prefer |
|---|---|
| Install dependencies | `composer-install` |
| Add a package | `composer-require` |
| Remove a package | `composer-remove` |
| Update dependencies | `composer-update` |
| Explain why a package is installed or blocked | `composer-explain` |
| Read dependency configuration | `composer://config` resource |

#### Guidance

- Use the MCP tools instead of shelling out to Composer when you want structured, compact output.
- Prefer `composer://config` when the user needs project dependency context rather than an action.
- This extension returns encoded structured payloads through Mate's core encoder.

---

### PHPStan Extension

Prefer these MCP tools over raw PHPStan CLI commands when the user is running static analysis.

| User intent | Prefer |
|---|---|
| Analyse the project, a directory, or one file | `phpstan-analyse` |
| Clear PHPStan cache | `phpstan-clear-cache` |

#### Guidance

- Use the MCP tools when the user wants analysis results in a compact, structured format.
- Use the `path` parameter on `phpstan-analyse` to target a single file or directory.
- This extension returns encoded structured payloads through Mate's core encoder.

---

### PHPUnit Extension

Prefer these MCP tools over raw PHPUnit CLI commands when the user is testing the project.

| User intent | Prefer |
|---|---|
| Run the full suite, one file, one class, or one method | `phpunit-run` |
| Discover available tests | `phpunit-list-tests` |

#### Guidance

- Use the MCP tools when the user wants test execution or discovery.
- Use the `file`, `class`, `method`, and `filter` parameters on `phpunit-run` instead of switching between multiple tool names.
- This extension returns encoded structured payloads through Mate's core encoder.

---

### Server Info

| Instead of...       | Use           |
|---------------------|---------------|
| `php -v`            | `server-info` |
| `php -m`            | `server-info` |
| `uname -s`          | `server-info` |

- Returns PHP version, OS, OS family, and loaded extensions in a single call

---

### Monolog Bridge

Use MCP tools instead of CLI for log analysis:

| Instead of...                     | Use                                              |
|-----------------------------------|--------------------------------------------------|
| `tail -f var/log/dev.log`         | `monolog-tail`                                   |
| `grep "error" var/log/*.log`      | `monolog-search` with term "error"               |
| `grep -E "pattern" var/log/*.log` | `monolog-search` with term "pattern", regex: true |

#### Benefits

- Structured output with parsed log entries
- Multi-file search across all logs at once
- Filter by environment, level, or channel

---

### Symfony Bridge

#### Container Introspection

| Instead of...                  | Use                |
|--------------------------------|--------------------|
| `bin/console debug:container`  | `symfony-services` |

- Direct access to compiled container
- Environment-aware (auto-detects dev/test/prod)
- Supports filtering by service ID or class name via query parameter

#### Profiler Access

When `symfony/http-kernel` is installed, profiler tools become available:

| Tool                        | Description                                             |
|-----------------------------|---------------------------------------------------------|
| `symfony-profiler-list`     | List and filter profiles by method, URL, IP, status, date range |
| `symfony-profiler-get`      | Get profile by token                                    |

**Resources:**
- `symfony-profiler://profile/{token}` - Full profile with collector list
- `symfony-profiler://profile/{token}/{collector}` - Collector-specific data

**Security:** Cookies, session data, auth headers, and sensitive env vars are automatically redacted.
