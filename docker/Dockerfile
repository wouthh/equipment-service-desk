FROM composer:2@sha256:d8f6343d3fae98107426bc49163ccad46ef85aabd4a27d80a74401fab4aba332 AS composer
FROM php:8.4-fpm-alpine@sha256:49734670eccf414af884c2a0c2e558401e228615f8028f1c9fca30a0d4fb1bc2 AS base
RUN apk add --no-cache icu-libs libpq libzip oniguruma libxml2 curl git make bash \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-install -j2 pdo_pgsql intl mbstring zip dom xml xmlwriter pcntl opcache \
    && apk del .build-deps \
    && addgroup -g 1000 desk && adduser -D -u 1000 -G desk desk
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
RUN chown desk:desk /app && mkdir /home/desk/.composer && chown desk:desk /home/desk/.composer
COPY docker/php.ini /usr/local/etc/php/conf.d/desk.ini
USER desk
FROM base AS development
CMD ["php-fpm", "-F"]
FROM base AS dependencies
COPY --chown=desk:desk composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY --chown=desk:desk . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts
FROM base AS production
COPY --from=dependencies --chown=desk:desk /app /app
ENV APP_ENV=prod APP_DEBUG=0
CMD ["php-fpm", "-F"]
