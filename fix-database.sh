#!/bin/bash

echo "🔧 Fixing Database Issues..."

# Navigate to API directory
cd /var/www/ltp-apis

# Clear all caches first
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Reset database completely
echo "🗂️ Resetting database..."
php artisan migrate:fresh --force

# Seed database with admin user
echo "🌱 Seeding database..."
php artisan db:seed --force

# Set proper permissions
echo "🔧 Setting permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache

# Clear caches again
echo "🧹 Final cache clear..."
php artisan config:clear

echo "✅ Database fixed successfully!"
echo ""
echo "🧪 Test the API:"
echo "curl -X POST http://35.222.74.225/api/v1/admin/login \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"email\":\"admin@letsturf.com\",\"password\":\"admin123\"}'"