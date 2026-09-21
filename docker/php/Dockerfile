FROM php:8.3-fpm-alpine

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
