# =========================================================
# Stage 1: Composer dependencies needed by Filament theme
# =========================================================

FROM composer:2 AS vendor-builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs


# =========================================================
# Stage 2: Vite frontend build
# =========================================================

FROM node:22-bookworm-slim AS frontend-builder

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

# The Filament theme imports CSS from vendor/filament,
# so make Composer dependencies available to Vite.
COPY --from=vendor-builder /app/vendor ./vendor

RUN npm run build


# =========================================================
# Stage 3: Nginx runtime
# =========================================================

FROM nginx:alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy the application's normal public files.
COPY public /var/www/html/public

# Replace/add public/build with the freshly generated Vite assets.
COPY --from=frontend-builder \
    /app/public/build \
    /var/www/html/public/build