#!/bin/sh
# Start PHP-FPM in the background
php-fpm -D

# Start nginx in the foreground (keeps container alive)
nginx -g 'daemon off;'
