# ==============================================================================
# Stage 1: Base PHP-FPM 8.3 Alpine with extensions required by ForgeMod Hub
# ==============================================================================
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        icu-libs \
        libzip \
        libxml2 \
        oniguruma \
        sqlite-libs \
        fcgi \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_sqlite \
        zip \
        intl \
        bcmath \
        opcache \
        xml \
        dom \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-forgemod.ini
COPY docker/php/zz-b1ms-fpm.conf /usr/local/etc/php-fpm.d/zz-b1ms-fpm.conf

WORKDIR /var/www/html

# ==============================================================================
# Stage 2: Development target (mounts workspace volume)
# ==============================================================================
FROM base AS dev
ENV APP_ENV=local

# ==============================================================================
# Stage 3: Frontend asset builder (used only during prod build, 0 runtime RAM)
# ==============================================================================
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json* vite.config.js ./
RUN npm install --no-audit --no-fund
COPY resources ./resources
COPY public ./public
RUN npm run build

# ==============================================================================
# Stage 4: Production target tuned for Azure B1ms (1 vCPU / 2 GiB RAM)
# ==============================================================================
FROM base AS prod
ENV APP_ENV=production

COPY composer.json composer.lock* ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

COPY . /var/www/html
COPY --from=assets /build/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p \
        storage/app/mods \
        storage/app/mods/_quarantine \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh
RUN chmod +x /usr/local/bin/entrypoint.prod.sh

ENTRYPOINT ["entrypoint.prod.sh"]
CMD ["php-fpm"]

