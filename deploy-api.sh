#!/bin/bash

set -e

echo "▶ Deploying LTP APIs..."

cd /var/www/ltp/ltp-apis

echo "▶ Stashing local changes (if any)..."
git stash push -u -m "auto-stash before deploy" || true

echo "▶ Pulling latest code..."
git fetch origin
git checkout v1
git pull --rebase origin v1

echo "▶ Installing dependencies..."
composer install --no-dev --optimize-autoloader

echo "▶ Running migrations..."
php artisan migrate --force

echo "▶ Clearing caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

echo "▶ Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "▶ Reloading PHP..."
systemctl reload php8.2-fpm

echo "✅ Deployment complete"