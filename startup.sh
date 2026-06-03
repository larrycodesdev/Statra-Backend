#!/bin/bash

cd /home/site/wwwroot

# Install composer dependencies
/usr/local/bin/composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 755 storage bootstrap/cache

# Clear and cache configurations
php artisan config:clear
php artisan config:cache
php artisan route:cache

# Run migrations
php artisan migrate --force