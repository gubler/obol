# ABOUTME: Multi-stage Dockerfile for the Obol application using FrankenPHP.
# ABOUTME: Builder stage installs dependencies, app stage runs the production server.

FROM dunglas/frankenphp:php8.4-bookworm AS builder

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && rm -rf /var/lib/apt/lists/*

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --classmap-authoritative \
    && composer dump-env prod \
    && php bin/console asset-map:compile

FROM dunglas/frankenphp:php8.4-bookworm

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=builder /app /app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p /app/public/uploads/logos \
    && chown -R www-data:www-data /app/var /app/public/uploads

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
