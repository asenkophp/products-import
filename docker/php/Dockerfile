FROM composer:2.8 AS composer_bin

# ---------- base: PHP 8.3 + расширения ----------
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype libpq rabbitmq-c \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
      icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev postgresql-dev rabbitmq-c-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" intl zip gd bcmath pdo_pgsql opcache pcntl \
 && pecl install amqp \
 && docker-php-ext-enable amqp \
 && apk del .build-deps

COPY docker/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/backend

# ---------- dev: composer + UID хоста для прав на примонтированные файлы ----------
FROM base AS dev
ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow \
 && groupmod -g ${GID} www-data \
 && usermod -u ${UID} -g ${GID} www-data
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

# ---------- vendor: зависимости без dev-пакетов ----------
FROM composer_bin AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------- prod: только код и vendor ----------
FROM base AS prod
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=vendor --chown=www-data:www-data /app /var/www/backend
USER www-data
