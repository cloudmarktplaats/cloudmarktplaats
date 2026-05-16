FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git curl libpng-dev libwebp-dev libjpeg-turbo-dev libzip-dev icu-dev \
    postgresql-dev oniguruma-dev gmp-dev autoconf g++ make linux-headers \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install gd pdo_pgsql intl zip bcmath pcntl gmp \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del autoconf g++ make linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
