FROM php:8.2-apache

# PDO MySQL extension for the app.
RUN docker-php-ext-install pdo pdo_mysql

# Fix MPM conflict: disable event/worker, enable only prefork (required for mod_php).
# Use DEBIAN_FRONTEND to avoid interactive prompts.
ENV DEBIAN_FRONTEND=noninteractive
RUN a2dismod mpm_event mpm_worker 2>/dev/null; \
    a2enmod mpm_prefork; \
    a2enmod rewrite; \
    sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf; \
    sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html

# Allow .htaccess overrides
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 80
CMD ["apache2-foreground"]
