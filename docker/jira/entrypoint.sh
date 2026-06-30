#!/bin/bash
set -e

cd /app

echo "[GateB] Waiting for MySQL..."
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
echo "[GateB] MySQL is ready."

if [ ! -f .env ]; then
  echo "[GateB] Creating .env from docker template..."
  cp /docker-env/.env.docker .env
fi

if [ ! -d vendor ]; then
  echo "[GateB] Running composer install..."
  composer config --global audit.block-insecure false
  composer config --no-plugins allow-plugins.topthink/think-installer true
  composer install --no-interaction --prefer-dist --no-dev
fi

mkdir -p data runtime/cache runtime/log runtime/temp static/upload
if [ ! -f data/install.lock ]; then
  echo "[GateB] Creating install.lock (DB imported via compose init)..."
  echo "1" > data/install.lock
fi

chmod -R 777 runtime static/upload data 2>/dev/null || true

if [ -x /app/docker/jira/init-jira-fixture.sh ]; then
  /app/docker/jira/init-jira-fixture.sh || true
fi

echo "[GateB] Starting php-fpm + nginx on :8090..."
echo "[GateB] Jira API: http://127.0.0.1:8090/rest/api/3/ (compat layer not implemented yet)"
php-fpm -D
exec nginx -g 'daemon off;'
