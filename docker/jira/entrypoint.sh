#!/bin/bash
set -e

cd /app

# Bind mount from host (CI) may trigger "dubious ownership" and break composer autoload.
git config --global --add safe.directory '*' 2>/dev/null || true
export COMPOSER_ROOT_VERSION="${COMPOSER_ROOT_VERSION:-dev-main}"

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

if [ ! -f think ]; then
  touch think
fi

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

if [ -x /app/docker/jira/init-jira-fixture.sh ]; then
  /app/docker/jira/init-jira-fixture.sh || true
fi

# Re-apply after init scripts: root may create runtime/log/* owned by root while php-fpm runs as www-data.
chmod -R 777 runtime static/upload data 2>/dev/null || true
chown -R www-data:www-data runtime static/upload data 2>/dev/null || true

if [ "${PEAR_ROLE:-app}" = "gateway" ]; then
  echo "[GateB] Starting GatewayWorker (WebSocket :2345)..."
  exec bash /app/docker/jira/start-gateway.sh
fi

echo "[GateB] Starting php-fpm + nginx on :8090..."
echo "[GateB] Jira API: http://127.0.0.1:8090/rest/api/3/"
php-fpm -D
exec nginx -g 'daemon off;'
