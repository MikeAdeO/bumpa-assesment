#!/bin/sh
set -e

cd /var/www/html

# .env is gitignored on purpose (it can hold secrets), so a fresh clone
# never has one. Materialize it from .env.example rather than requiring
# the reviewer to do this by hand before `docker compose up` works.
if [ ! -f .env ]; then
  echo "[entrypoint] .env not found, copying from .env.example"
  cp .env.example .env
fi

# The named `vendor` volume declared in docker-compose.yml is normally
# already populated (Docker seeds a volume from the image on first
# creation), so this only fires if that volume was wiped or the image
# was built without running composer install for some reason.
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] vendor/autoload.php missing, running composer install"
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generate APP_KEY if the .env we just materialized (or one already on
# disk) doesn't have one yet.
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "[entrypoint] generating APP_KEY"
  php artisan key:generate --force
fi

# depends_on: condition: service_healthy already waits for Postgres to
# accept connections, but a short additional wait here is cheap insurance
# against races on slower first boots.
if [ -n "$DB_HOST" ]; then
  echo "[entrypoint] waiting for database at ${DB_HOST}:${DB_PORT:-5432}"
  attempt=0
  until php -r "exit(@fsockopen(getenv('DB_HOST'), (int) getenv('DB_PORT')) ? 0 : 1);" >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
      echo "[entrypoint] database did not become reachable in time" >&2
      exit 1
    fi
    sleep 1
  done
fi

# "migrate" is this script's own sub-command, used by the one-shot
# `migrate` compose service — it is NOT an artisan command name. Keeping
# migrations in a dedicated short-lived service (rather than running them
# from both `app` and `worker` on every boot) avoids two containers
# racing to run the same migration concurrently.
if [ "$1" = "migrate" ]; then
  echo "[entrypoint] running migrations"
  php artisan migrate --force

  echo "[entrypoint] seeding reference data"
  php artisan db:seed --force

  echo "[entrypoint] migrate step complete"
  exit 0
fi

exec "$@"
