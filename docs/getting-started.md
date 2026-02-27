# Getting Started

This guide covers setting up Obol for local development.

## Prerequisites

- **PHP 8.5+** with extensions: `intl`, `mbstring`, `pdo_sqlite`
- **[Composer](https://getcomposer.org/)** v2
- **[mise](https://mise.jdx.dev/)** (optional, for task shortcuts)
- **[Symfony CLI](https://symfony.com/download)** (optional, for `symfony serve`)
- **[MkDocs](https://www.mkdocs.org/)** with Material theme (optional, for docs)

## Setup

### 1. Clone and install dependencies

```bash
git clone ssh://git@code.dev88.work:222/dev88/obol.git
cd obol
composer install
```

`composer install` automatically installs git hooks via Captain Hook.

### 2. Run migrations

The default `.env` configures SQLite, so no external database is needed for development:

```bash
php bin/console doctrine:migrations:migrate
```

This creates and migrates `var/data_dev.db`.

### 3. Load fixtures (optional)

```bash
php bin/console doctrine:fixtures:load
```

This seeds the database with sample categories, subscriptions, and payments for development.

### 4. Start the dev server

```bash
symfony serve
# or
php -S localhost:8000 -t public
```

The application should be running at `http://localhost:8000`.

## Database Configuration

**Development and tests** use SQLite by default (configured in `.env`). No external database server is required.

**Production** uses PostgreSQL 16, configured via environment variables in the Docker Compose stack. See [Deployment](deployment.md) for details.

To use PostgreSQL locally instead of SQLite, create `.env.local` with:

```bash
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/obol?serverVersion=16&charset=utf8"
```

You can start a local PostgreSQL instance via Docker Compose:

```bash
docker compose up -d database
```

!!! note
    There is a [planned migration to PostgreSQL for all environments](https://code.dev88.work/dev88/obol/issues/57) to eliminate the risk of SQLite/PostgreSQL dialect differences.

## Verify the setup

Run the test suite to confirm everything is working:

```bash
mise run test
# or without mise:
php vendor/bin/pest --compact
```

Tests use a separate SQLite database (`var/data_test.db`), automatically created and migrated at test bootstrap.

## Working on documentation

The documentation uses MkDocs with the Material theme. Install it with:

```bash
pipx install mkdocs-material
# or
pip install mkdocs-material
```

Then serve locally:

```bash
mise run docs:serve
```

This starts a live-reloading server at `http://127.0.0.1:8000`.
