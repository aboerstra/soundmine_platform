# Development/test tooling only. Composer's image includes PHP CLI and Composer.
FROM composer:2@sha256:0c8e663f14c94d07b3c45b175c51f5a14718508f21fe4dd7e54f09488dd0ed71
USER root
RUN apk add --no-cache icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev \
    && docker-php-ext-install intl pdo_pgsql \
    && apk del .build-deps
WORKDIR /app
