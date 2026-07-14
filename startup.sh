#!/bin/bash

cp /home/site/wwwroot/nginx/default /etc/nginx/sites-enabled/default
nginx -s reload

chmod -R 775 /home/site/wwwroot/storage
chmod -R 775 /home/site/wwwroot/bootstrap/cache

mkdir -p /home/site/wwwroot/storage/framework/views \
         /home/site/wwwroot/storage/framework/cache \
         /home/site/wwwroot/storage/framework/sessions \
         /home/site/wwwroot/storage/logs \
         /home/site/wwwroot/bootstrap/cache

cd /home/site/wwwroot

php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force

# Run the Laravel scheduler and queue worker as background processes
php artisan schedule:work >> /home/site/wwwroot/storage/logs/scheduler.log 2>&1 &
php artisan queue:work --sleep=3 --tries=3 >> /home/site/wwwroot/storage/logs/queue.log 2>&1 &
