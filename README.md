# Obol

A subscription management application for tracking recurring payments, payment history, and subscription lifecycle events.

Built with Symfony 8.0 and PHP 8.5+.

## Features

- Track subscriptions with cost, payment period, and metadata
- Record and verify individual payments
- Organize subscriptions by category
- Archive/unarchive subscriptions without deletion
- Full audit trail via event sourcing (updates, cost changes, archive/unarchive)
- CQRS via Symfony Messenger (separate query and command buses)

## Requirements

- PHP 8.5+
- PostgreSQL 16+
- [Composer](https://getcomposer.org/)
- [Symfony CLI](https://symfony.com/download) (optional, for `symfony serve`)
- [mise](https://mise.jdx.dev/) (optional, for task shortcuts)

## Setup

```bash
# Install dependencies
composer install

# Start the database
docker compose up -d

# Run migrations
php bin/console doctrine:migrations:migrate

# Load fixtures (development only)
php bin/console doctrine:fixtures:load

# Start the dev server
symfony serve
```

## Development

### Running Tests

```bash
mise run test                          # All tests
mise run test --testsuite=Unit         # Unit tests
mise run test --testsuite=Feature      # Feature tests
mise run test --testsuite=Integration  # Integration tests
mise run test tests/Unit/SomeTest.php  # Single file
```

### Code Quality

```bash
mise run sa       # PHPStan static analysis (level 9)
mise run cs       # PHP CS Fixer
mise run cs:twig  # Twig CS Fixer
mise run rector   # Rector automated refactoring
```

### Without mise

```bash
php vendor/bin/phpunit
php vendor/bin/phpstan analyse
php vendor/bin/php-cs-fixer fix
php vendor/bin/rector
```

## Architecture

The domain model centers on four entities, all using ULID primary keys:

- **Subscription** — core entity with immutable properties (`public private(set)`)
- **Payment** — individual payment records (verified or generated)
- **Category** — groups subscriptions
- **SubscriptionEvent** — audit log for all subscription state changes

State changes on `Subscription` go through dedicated methods (`update()`, `archive()`, `recordPayment()`) which emit `SubscriptionEvent` records automatically.

## Documentation

Full developer documentation is at [docs.dev88.work/obol](https://docs.dev88.work/obol).

### Working on docs locally

Requires [MkDocs](https://www.mkdocs.org/) with the Material theme:

```bash
pipx install mkdocs-material
# or
pip install mkdocs-material
```

```bash
mise run docs:serve   # Live preview at http://127.0.0.1:8000
mise run docs:build   # Build to site/
mise run docs:deploy  # Build and deploy to docs.dev88.work/obol
```

## License

Proprietary.
