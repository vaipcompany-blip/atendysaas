FROM php:8.2-fpm

# Install nginx and PHP extensions
RUN apt-get update && apt-get install -y nginx && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql

# nginx site config
COPY docker/nginx.conf /etc/nginx/sites-available/default

WORKDIR /var/www/html
COPY . /var/www/html

# Ensure PHP runtime user can write logs/cache files.
RUN mkdir -p /var/www/html/storage/logs \
	&& chown -R www-data:www-data /var/www/html/storage \
	&& chmod -R 775 /var/www/html/storage

# Startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
