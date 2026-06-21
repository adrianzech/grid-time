ARG PHP_VERSION=8.5
ARG COMPOSER_VERSION=2.8
ARG APP_ENV=prod
ARG APP_DEBUG=0

FROM composer:${COMPOSER_VERSION} AS composer
FROM dunglas/frankenphp:php${PHP_VERSION}-alpine AS vendor
ARG APP_ENV

WORKDIR /srv/app

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl

COPY backend/ ./

RUN \
    cp public/bundles/apiplatform/grid-time.css /tmp/grid-time-api.css && \
    if [ "$APP_ENV" = "dev" ]; then \
        composer install --no-interaction --prefer-dist --no-scripts; \
    else \
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts; \
    fi && \
    php bin/console assets:install public --env=${APP_ENV} --no-debug && \
    cp /tmp/grid-time-api.css public/bundles/apiplatform/grid-time.css

FROM dunglas/frankenphp:php${PHP_VERSION}-alpine AS runtime
ARG APP_ENV
ARG APP_DEBUG

WORKDIR /srv/app

RUN install-php-extensions \
    intl \
    opcache \
    pdo_pgsql

COPY --from=vendor --chown=www-data:www-data /srv/app /srv/app
COPY docker/build/backend/Caddyfile /etc/frankenphp/Caddyfile

ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=${APP_DEBUG} \
    SERVER_NAME=:8000 \
    FRANKENPHP_CONFIG="worker ./public/index.php"

RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data var && \
    chmod -R 755 var

HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8000/healthz || exit 1

EXPOSE 8000
