# =========================================================
# Stage 1: Laravel / PHP application
# =========================================================

FROM php:8.3-fpm AS app-base

ENV DEBIAN_FRONTEND=noninteractive

WORKDIR /var/www/html

# ---------------------------------------------------------
# PostgreSQL PGDG Repository
# Required so pg_dump matches PostgreSQL 16 server
# ---------------------------------------------------------

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl \
        --fail \
        --silent \
        --show-error \
        --location \
        -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
        https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && . /etc/os-release \
    && printf '%s\n' \
        "Types: deb" \
        "URIs: https://apt.postgresql.org/pub/repos/apt" \
        "Suites: ${VERSION_CODENAME}-pgdg" \
        "Components: main" \
        "Signed-By: /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc" \
        > /etc/apt/sources.list.d/pgdg.sources

# ---------------------------------------------------------
# System Dependencies + PHP Extensions
# ---------------------------------------------------------

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        zip \
        unzip \
        postgresql-client-16 \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        imagemagick \
        libmagickwand-dev \
        librsvg2-bin \
        pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------
# PECL Extensions
# Redis   = queue/cache/session
# Imagick = SVG -> PNG badge conversion
# ---------------------------------------------------------

RUN pecl install redis imagick \
    && docker-php-ext-enable redis imagick

# ---------------------------------------------------------
# Composer
# ---------------------------------------------------------

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------
# PHP Configuration
# ---------------------------------------------------------

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# ---------------------------------------------------------
# Composer Dependencies
# ---------------------------------------------------------

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ---------------------------------------------------------
# Application Source
# ---------------------------------------------------------

COPY . .

# ---------------------------------------------------------
# Laravel Runtime Preparation
# ---------------------------------------------------------

RUN composer dump-autoload --optimize \
    && mkdir -p \
        storage/app/public \
        storage/app/backups \
        storage/app/backup-temp \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/framework/livewire-tmp \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache


# =========================================================
# Stage 2: Frontend / Vite Build
# =========================================================

FROM node:22-bookworm-slim AS frontend-builder

WORKDIR /app

# Install JS dependencies first for Docker layer caching.
COPY package.json package-lock.json ./

RUN npm ci

# Copy application source.
COPY . .

# Filament theme imports CSS from vendor/filament,
# so vendor must also exist in the frontend build stage.
COPY --from=app-base /var/www/html/vendor ./vendor

# Build Vite assets, including:
# resources/css/filament/admin/theme.css
RUN npm run build


# =========================================================
# Stage 3: Final Runtime Image
# =========================================================

FROM app-base AS runtime

WORKDIR /var/www/html

# Copy the freshly generated Vite build into the runtime image.
COPY --chown=www-data:www-data \
    --from=frontend-builder \
    /app/public/build \
    /var/www/html/public/build

EXPOSE 9000
CMD ["php-fpm"]
