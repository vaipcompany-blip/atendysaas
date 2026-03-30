FROM php:8.2-fpm

# Install nginx and PHP extensions
RUN apt-get update && apt-get install -y nginx && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql

# nginx site config
COPY docker/nginx.conf /etc/nginx/sites-available/default

WORKDIR /var/www/html
COPY . /var/www/html

# Startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
