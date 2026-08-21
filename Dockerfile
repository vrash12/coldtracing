# syntax=docker/dockerfile:1.7

FROM php:8.4-fpm-bookworm

ARG APP_DIR=/var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libonig-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR ${APP_DIR}

# Cache PHP dependencies in the image. The entrypoint runs a quick Composer
# reconciliation so the development bind mount and named vendor volume remain
# synchronized when composer.json or composer.lock changes.
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts

COPY . .
COPY docker/php.ini /usr/local/etc/php/conf.d/99-coldtrace.ini
COPY docker/entrypoint.sh /usr/local/bin/coldtrace-entrypoint

RUN chmod +x /usr/local/bin/coldtrace-entrypoint \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["coldtrace-entrypoint"]
CMD ["php-fpm"]

EXPOSE 9000
