#!/bin/bash

echo "🔥 Completely resetting database..."

cd /var/www/ltp-apis

# Drop and recreate database
echo "💥 Dropping database..."
sudo -u postgres psql -c "DROP DATABASE IF EXISTS ltp_v1;"
sudo -u postgres psql -c "CREATE DATABASE ltp_v1;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ltp_v1 TO ltpuser;"

# Clear all Laravel caches
echo "🧹 Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run fresh migrations
echo "🗂️ Running fresh migrations..."
php artisan migrate --force

# Seed database
echo "🌱 Seeding database..."
php artisan db:seed --force

# Set permissions
echo "🔧 Setting permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache

echo "✅ Database completely reset!"
echo ""
echo "🧪 Test login:"
echo "curl -X POST http://35.222.74.225/api/v1/admin/login \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"email\":\"admin@letsturf.com\",\"password\":\"admin123\"}'"