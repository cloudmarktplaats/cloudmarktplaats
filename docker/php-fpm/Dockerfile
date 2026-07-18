FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git curl libpng-dev libwebp-dev libjpeg-turbo-dev libzip-dev icu-dev \
    postgresql-dev oniguruma-dev gmp-dev autoconf g++ make linux-headers \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install gd pdo_pgsql intl zip bcmath pcntl gmp exif \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del autoconf g++ make linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Upload limits. PHP's defaults (upload_max_filesize=2M, post_max_size=8M) are
# far below what this app promises: the listing wizard validates photos at
# max:8192 (8MB) and allows 10 per submit. PHP silently discarded anything
# over 2M *before* Laravel saw it, so the wizard reported "photos.0 failed to
# upload" and every phone photo (3-8MB) was rejected — only small screenshots
# ever got through. nginx was raised to match this in docker/nginx/default.conf;
# both layers must agree or the smaller one silently wins.
#
# These cannot be set from PHP: upload_max_filesize/post_max_size are read
# before the script runs, so ini_set() has no effect. Hence an ini file baked
# into the image (prod builds it from this Dockerfile).
#
# 88M = 10 photos x 8M + form overhead.
RUN { \
      echo "upload_max_filesize = 8M"; \
      echo "post_max_size = 88M"; \
    } > /usr/local/etc/php/conf.d/99-uploads.ini

WORKDIR /app
