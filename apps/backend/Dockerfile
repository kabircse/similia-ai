FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-libs \
    libpq \
    libzip \
    oniguruma \
    unzip \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    curl-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    && docker-php-ext-install \
    bcmath \
    curl \
    intl \
    mbstring \
    opcache \
    pdo_pgsql \
    pgsql \
    zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY apps/backend/composer.json apps/backend/composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY apps/backend .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY apps/backend/docker/php/php.ini /usr/local/etc/php/conf.d/99-similia.ini
COPY apps/backend/docker/entrypoint.sh /usr/local/bin/similia-entrypoint

RUN chmod +x /usr/local/bin/similia-entrypoint

EXPOSE 9000

ENTRYPOINT ["similia-entrypoint"]
CMD ["php-fpm"]
