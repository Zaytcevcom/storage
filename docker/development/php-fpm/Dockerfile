FROM php:8.1-fpm-alpine

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

RUN docker-php-ext-install pdo pdo_mysql

COPY ./common/php/conf.d /usr/local/etc/php/conf.d

WORKDIR /app