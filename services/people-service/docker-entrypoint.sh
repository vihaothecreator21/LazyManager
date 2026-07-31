#!/bin/sh
set -eu

# Fail fast nếu JWT_SECRET không được truyền vào — không hard-code secret.
if [ -z "${JWT_SECRET:-}" ]; then
  echo "ERROR: JWT_SECRET chưa được cấu hình. Dừng container." >&2
  exit 1
fi

cat > .env <<EOF
APP_NAME="${APP_NAME:-LazyManager People Service}"
APP_ENV=${APP_ENV:-local}
APP_DEBUG=${APP_DEBUG:-false}
APP_KEY=${APP_KEY}
APP_URL=${APP_URL:-http://localhost}
LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_STACK=${LOG_STACK:-single}
LOG_LEVEL=${LOG_LEVEL:-debug}
CACHE_STORE=${CACHE_STORE:-file}
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-people-db}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-people_db}
DB_USERNAME=${DB_USERNAME:-lazymanager}
DB_PASSWORD=${DB_PASSWORD:-lazymanager}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
SESSION_DRIVER=${SESSION_DRIVER:-file}
JWT_SECRET=${JWT_SECRET}
JWT_ISSUER=${JWT_ISSUER:-lazymanager-people-service}
JWT_AUDIENCE=${JWT_AUDIENCE:-lazymanager}
JWT_TTL_MINUTES=${JWT_TTL_MINUTES:-15}
EOF

php artisan migrate --force

if [ "${RUN_DEMO_SEEDERS:-false}" = "true" ]; then
  php artisan db:seed --force
fi

exec "$@"
