FROM php:8.2-apache

# PDO MySQL extension for the app.
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite and fix MPM conflict in Docker.
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html

EXPOSE 80
