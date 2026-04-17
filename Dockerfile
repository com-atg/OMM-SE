# ── Stage 1: JS/CSS assets ───────────────────────────────────────────────────
FROM node:22-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

# ── Stage 2: PHP Composer dependencies ───────────────────────────────────────
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize

# ── Stage 3: Runtime ─────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

# Upgrade all base-image packages to pull in latest security patches,
# then install only what the runtime actually needs (curl intentionally omitted).
# Upgrade wheel (pulled in by supervisor's Python dep) to patch CVE-2026-24049.
RUN apk upgrade --no-cache \
    && apk add --no-cache \
        nginx \
        supervisor \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
        linux-headers \
    && docker-php-ext-install \
        mbstring \
        zip \
        gd \
        opcache \
        pcntl \
        bcmath \
        intl \
    && pip install --upgrade --no-cache-dir --break-system-packages setuptools wheel 2>/dev/null || true \
    && rm -rf /usr/lib/python*/site-packages/setuptools/_vendor/wheel-* \
              /usr/lib/python*/site-packages/pip/_vendor/wheel-* 2>/dev/null || true

# PHP configuration
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy application (Composer + built assets)
COPY --from=composer-builder /app /var/www/html
COPY --from=node-builder /app/public/build /var/www/html/public/build

# Prepare writable directories for www-data.
# Nginx temp paths are redirected to /tmp so it can run without root.
RUN mkdir -p \
        storage/logs \
        storage/framework/{cache,sessions,views} \
        bootstrap/cache \
        /tmp/nginx/{client_body,proxy,fastcgi,uwsgi,scgi} \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
        /tmp/nginx \
        /var/lib/nginx \
        /var/log/nginx \
    && chmod -R 775 storage bootstrap/cache \
    && sed -i 's|^pid .*;|pid /tmp/nginx.pid;|' /etc/nginx/nginx.conf \
    && sed -i 's|^error_log .*;|error_log /dev/stderr warn;|' /etc/nginx/nginx.conf

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Drop privileges — all processes (nginx on 8080, php-fpm, supervisord) run as www-data
USER www-data

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
