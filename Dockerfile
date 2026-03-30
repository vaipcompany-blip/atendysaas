FROM php:8.2-apache

# PDO MySQL extension for the app.
RUN docker-php-ext-install pdo pdo_mysql

# Fix MPM conflict: wipe ALL mpm_* symlinks then enable only prefork (required for mod_php).
RUN find /etc/apache2/mods-enabled -name 'mpm_*' -delete \
    && a2enmod mpm_prefork rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
