#!/bin/bash
set -e

cd /app

echo "[HistoryV] Waiting for MySQL..."
until php -r "
try {
  new PDO(
    'mysql:host=${PEAR_DB_HOST:-mysql};port=${PEAR_DB_PORT:-3306}',
    '${PEAR_DB_USER:-root}',
    '${PEAR_DB_PASS:-root}'
  );
  exit(0);
} catch (Exception \$e) {
  exit(1);
}
" 2>/dev/null; do
  sleep 2
done
echo "[HistoryV] MySQL is ready."

php /app/docker/historyv/fix-static-urls.php 2>/dev/null || true

if [ ! -f .env ]; then
  echo "[HistoryV] Creating .env from docker template..."
  cp /docker-env/.env.docker .env
fi

if [ ! -d vendor ]; then
  echo "[HistoryV] Running composer install..."
  composer config --global audit.block-insecure false
  composer config --no-plugins allow-plugins.topthink/think-installer true
  composer install --no-interaction --prefer-dist --no-dev
fi

mkdir -p data runtime/cache runtime/log runtime/temp static/upload
if [ ! -f data/install.lock ]; then
  echo "[HistoryV] Creating install.lock (DB imported via compose init)..."
  echo "1" > data/install.lock
fi

chmod -R 777 runtime static/upload data 2>/dev/null || true

if [ -f static/dist/index.html ]; then
  cp -f static/dist/index.html index.html
else
  echo "[HistoryV] WARNING: static/dist/index.html not found."
  echo "  Run: docker compose --profile build run --rm frontend-build"
  echo "  Or build pearProject (HistoryV) locally and mount dist."
fi

echo "[HistoryV] Starting php-fpm + nginx..."
php-fpm -D
exec nginx -g 'daemon off;'
