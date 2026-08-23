#!/bin/sh
set -e

# Fix storage symlink — always recreate to handle cross-platform path issues
rm -f /var/www/html/public/storage
ln -s /var/www/html/storage/app/public /var/www/html/public/storage

# Ensure storage directories exist and are writable
mkdir -p /var/www/html/storage/app/public \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Run database migrations
php /var/www/html/artisan migrate --force --no-interaction 2>&1 || true

# Optimise
php /var/www/html/artisan config:cache  --no-interaction 2>&1 || true
php /var/www/html/artisan route:cache   --no-interaction 2>&1 || true
php /var/www/html/artisan view:cache    --no-interaction 2>&1 || true

exec supervisord -c /etc/supervisord.conf
