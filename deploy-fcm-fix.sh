#!/bin/bash
# Deploy FcmService Fix to Production Server

echo "🚀 Deploying FcmService fix..."

# Upload updated FcmService.php
scp app/Services/FcmService.php user@server:/var/www/ltp/ltp-apis/app/Services/

# Clear Laravel cache on server
ssh user@server << 'EOF'
cd /var/www/ltp/ltp-apis
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
sudo systemctl restart php8.2-fpm
EOF

echo "✅ Deployment complete!"
