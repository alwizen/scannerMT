#!/bin/sh
set -e

# If vendor not present, install dependencies
if [ ! -d "/var/www/html/vendor" ]; then
  echo "Running composer install..."
  composer install --no-interaction --prefer-dist --optimize-autoloader || true
fi

# Ensure writable Laravel runtime directories without taking ownership from the host.
mkdir -p \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache

chmod -R a+rwX /var/www/html/storage /var/www/html/bootstrap/cache || true

exec "$@"
