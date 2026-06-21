#!/bin/sh
set -e

# If vendor not present, install dependencies
if [ ! -d "/var/www/html/vendor" ]; then
  echo "Running composer install..."
  composer install --no-interaction --prefer-dist --optimize-autoloader || true
fi

# Ensure permissions for storage and cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

exec "$@"
