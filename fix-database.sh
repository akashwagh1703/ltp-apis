#!/bin/bash

echo "🔧 Fixing Database and Routing Issues..."

# Navigate to API directory
cd /var/www/ltp-apis

# Fix bootstrap/app.php for proper API routing
echo "🔧 Fixing bootstrap configuration..."
cat > bootstrap/app.php << 'EOF'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\Cors::class,
        ]);
        
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'player.auth' => \App\Http\Middleware\PlayerAuth::class,
            'owner.auth' => \App\Http\Middleware\OwnerAuth::class,
            'log.activity' => \App\Http\Middleware\LogActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
EOF

# Create .htaccess for proper routing
echo "🔧 Creating .htaccess..."
cat > public/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOF

# Clear all caches
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
sudo chown -R www-data:www-data storage bootstrap/cache public
sudo chmod -R 755 storage bootstrap/cache public

# Clear caches again
echo "🧹 Final cache clear..."
php artisan config:clear

# Check routes
echo "📋 Checking routes..."
php artisan route:list | grep admin | head -5

echo "✅ Database and routing fixed successfully!"
echo ""
echo "🧪 Test the API:"
echo "curl -X POST http://35.222.74.225/api/v1/admin/login \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"email\":\"admin@letsturf.com\",\"password\":\"admin123\"}'"