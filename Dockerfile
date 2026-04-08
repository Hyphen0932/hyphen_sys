FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json ./

RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

COPY --chown=www-data:www-data . /var/www/html/hyphen_sys
COPY --from=vendor /app/vendor /var/www/html/hyphen_sys/vendor

RUN mkdir -p /usr/local/share/hyphen_sys-default-user-image \
    && cp -a /var/www/html/hyphen_sys/assets/user_image/. /usr/local/share/hyphen_sys-default-user-image/

WORKDIR /var/www/html/hyphen_sys

EXPOSE 80