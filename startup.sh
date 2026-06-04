#!/bin/bash

cd /home/site/wwwroot

# Download and run composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp --filename=composer
/tmp/composer install --no-dev --optimize-autoloader

# Create required directories and set permissions
mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions
mkdir -p storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Fix nginx document root for Laravel
cat > /etc/nginx/sites-enabled/default << 'EOF'
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot/public;
    index index.php index.html index.htm;
    server_name _;
    port_in_redirect off;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    error_page 500 502 503 504 /50x.html;
    location = /50x.html {
        root /html/;
    }

    location ~ /\.git {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~* [^/]\.php(/|$) {
        fastcgi_split_path_info ^(.+?\.[Pp][Hh][Pp])(|/.*)$;
        fastcgi_pass 127.0.0.1:9000;
        include fastcgi_params;
        fastcgi_param HTTP_PROXY "";
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_intercept_errors on;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 3600;
        fastcgi_read_timeout 3600;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }
}
EOF

nginx -s reload

# Clear and cache configurations
php artisan config:clear
php artisan config:cache
php artisan route:cache

# Run migrations
php artisan migrate --force