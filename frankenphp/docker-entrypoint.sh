#!/bin/sh
# ABOUTME: FrankenPHP container entrypoint — waits for the database then runs migrations.
# ABOUTME: Installs vendor dir on first boot; no-ops in prod images where vendor is baked in.
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		if [ "$(find ./migrations -iname '*.php' -print -quit)" ]; then
			# Migrating is opted into, never a default. Every container built from this image
			# runs this entrypoint, so a container that migrates unless told otherwise means
			# any service added later races the others against one database until someone
			# remembers to opt it out. Exactly one container sets this, and it is the one the
			# rest of the stack waits on.
			#
			# A failure is fatal on purpose. A half-migrated schema is not a degraded
			# application, it is a broken one: sessions and the cache pool are database tables
			# on the request path, so a missing table means every request throws - while the
			# healthcheck, which probes Caddy rather than the application, still reports
			# healthy. Exiting here fails the deploy loudly instead of serving traffic that
			# cannot work.
			if [ "${OBOL_RUN_MIGRATIONS:-0}" = '1' ]; then
				php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
			fi

			# Then every container checks the schema for itself, migrator or not, and refuses to
			# start against one older than its code expects. This is what makes opting in safe:
			# configure nobody to migrate and the whole stack declines to boot, rather than
			# quietly serving against a stale schema. It also keeps a non-migrating container
			# from trusting the migrator's exit code over the state of the database.
			#
			# Deliberately without --fail-on-unregistered: a rollback to an older image leaves
			# migrations in the database that its codebase does not carry, and that has to stay
			# bootable. The check asks "is anything of mine unapplied", not "does the database
			# match me exactly".
			php bin/console doctrine:migrations:up-to-date
		fi
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
