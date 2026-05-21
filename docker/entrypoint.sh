#!/usr/bin/env sh
set -eu

# Run DB migrations on boot (safe to re-run)
if [ "${APP_ENV:-}" = "prod" ] || [ "${RAILWAY_ENVIRONMENT:-}" != "" ]; then
  if [ "${DATABASE_URL:-}" != "" ]; then
    echo "[entrypoint] Running migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
  fi
fi

exec "$@"

