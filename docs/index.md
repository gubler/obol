# Obol

Obol is a subscription management application for tracking recurring payments, payment history, and subscription lifecycle events. It is a personal homelab tool built with a focus on strict type safety, thorough testing, and clean architecture.

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Language | PHP 8.5+ |
| Framework | Symfony 8.0 |
| Database | SQLite (dev/test), PostgreSQL 16 (production) |
| Web Server | FrankenPHP (Caddy + PHP in one binary) |
| Frontend | Tailwind CSS v4, Hotwired Stimulus, Hotwired Turbo |
| Asset Pipeline | Symfony AssetMapper (no build step) |
| Testing | Pest PHP, Foundry, DAMA DoctrineTestBundle |
| Static Analysis | PHPStan level 9 |
| CI/CD | Gitea Actions |
| Containerization | Docker (multi-stage build) |

## Quick Links

- [Getting Started](getting-started.md) — set up a local development environment
- [Architecture](architecture/index.md) — domain model, CQRS, controllers
- [Frontend](frontend.md) — Tailwind, Stimulus, Turbo, AssetMapper
- [Deployment](deployment.md) — Docker, compose, environment variables
- [CI/CD](ci-cd.md) — Gitea Actions workflow
- [Development](development/index.md) — standards, testing, git hooks, mise tasks
- [Deploying Updates](operations/updates.md) — new versions and migrations
