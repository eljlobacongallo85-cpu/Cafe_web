#!/usr/bin/env sh
set -eu

export PORT="${PORT:-80}"

if [ -f /etc/nginx/conf.d/symfony.conf ]; then
  envsubst '${PORT}' < /etc/nginx/conf.d/symfony.conf > /etc/nginx/conf.d/default.conf
  rm -f /etc/nginx/conf.d/symfony.conf
fi

# Run DB migrations on boot (safe to re-run)
if [ "${DATABASE_URL:-}" != "" ]; then
  echo "[entrypoint] Running migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
fi

php-fpm -D
exec nginx -g 'daemon off;'

