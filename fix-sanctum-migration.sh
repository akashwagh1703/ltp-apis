#!/bin/bash

echo "🔧 Fixing Sanctum Migration Conflict..."

cd /var/www/ltp-apis

# Check if personal_access_tokens table exists
echo "🔍 Checking existing tables..."
TABLE_EXISTS=$(sudo -u postgres psql ltp_v1 -t -c "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'personal_access_tokens');" | xargs)

if [ "$TABLE_EXISTS" = "t" ]; then
    echo "📋 Table exists, marking migration as run..."
    # Mark the existing migration as run
    php artisan migrate:status
    
    # Insert migration record manually if needed
    sudo -u postgres psql ltp_v1 -c "
    INSERT INTO migrations (migration, batch) 
    VALUES ('2025_12_09_121829_create_personal_access_tokens_table', 1) 
    ON CONFLICT (migration) DO NOTHING;
    "
else
    echo "📋 Table doesn't exist, will create it..."
fi

# Prevent Sanctum from auto-publishing migrations
echo "🚫 Preventing auto-migration..."
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag="sanctum-migrations" --force 2>/dev/null || true

# Remove any duplicate sanctum migrations
echo "🧹 Cleaning duplicate migrations..."
find database/migrations/ -name "*create_personal_access_tokens_table.php" -not -name "2025_12_09_121829_create_personal_access_tokens_table.php" -delete 2>/dev/null || true

# Run migrations
echo "🗂️ Running migrations..."
php artisan migrate --force

# Seed database
echo "🌱 Seeding database..."
php artisan db:seed --force

echo "✅ Sanctum migration fixed!"