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

# ── Vendor cache ─────────────────────────────────────────────────────────────
# vendor/ is excluded from the deployment artifact (saves ~147MB per deploy).
# We cache it under /home/vendor-cache/<composer.lock hash> so Composer only
# runs when dependencies actually change — otherwise we just copy from cache.
LOCK_HASH=$(md5sum composer.lock | cut -d' ' -f1)
CACHE_DIR="/home/vendor-cache/$LOCK_HASH"

if [ -d "$CACHE_DIR" ]; then
    echo "[startup] Using cached vendor ($LOCK_HASH)"
    cp -r "$CACHE_DIR" vendor
else
    echo "[startup] composer.lock changed — running composer install"
    composer install --no-dev --optimize-autoloader --prefer-dist --no-progress --no-interaction
    # Save to cache for next deploy
    mkdir -p /home/vendor-cache
    cp -r vendor "$CACHE_DIR"
    # Keep only the 3 most recent caches so /home doesn't fill up
    ls -dt /home/vendor-cache/*/ 2>/dev/null | tail -n +4 | xargs rm -rf
fi
# ─────────────────────────────────────────────────────────────────────────────

php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan migrate --force

# Run the Laravel scheduler and queue worker as background processes
php artisan schedule:work >> /home/site/wwwroot/storage/logs/scheduler.log 2>&1 &
php artisan queue:work --sleep=3 --tries=3 >> /home/site/wwwroot/storage/logs/queue.log 2>&1 &
