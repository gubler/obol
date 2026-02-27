#!/bin/sh
# ABOUTME: Docker entrypoint script for the Obol application.
# ABOUTME: Runs database migrations before starting the application server.

set -e

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Starting application server..."
exec "$@"
