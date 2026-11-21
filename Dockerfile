FROM php:8.4-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pdo_mysql intl zip bcmath gd opcache \
 && a2enmod rewrite headers \
 && sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1 PORT=10000

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN composer dump-autoload --optimize --no-dev \
 && php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache public/uploads

EXPOSE 10000
CMD ["/var/www/html/docker/entrypoint.sh"]
