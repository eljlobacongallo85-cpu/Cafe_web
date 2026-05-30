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

# Start orders WebSocket worker (used by mobile realtime updates)
# Keep it auto-restarting and send logs to container stdout for Railway visibility.
echo "[entrypoint] Starting WebSocket supervisor on 127.0.0.1:8081..."
(
  while true; do
    echo "[entrypoint] Launching app:websocket:orders..."
    code=0
    su -s /bin/sh www-data -c "php bin/console app:websocket:orders --host=127.0.0.1 --port=8081" || code=$?
    echo "[entrypoint] WebSocket worker exited with code ${code}. Restarting in 2s..."
    sleep 2
  done
) &

php-fpm -D
exec nginx -g 'daemon off;'
