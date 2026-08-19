FROM php:8.2-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    mysql-client \
    nginx \
    supervisor

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html/storage \
 && chmod -R 755 /var/www/html/bootstrap/cache \
 && mkdir -p /tmp/nginx/client_body /tmp/nginx/proxy /tmp/nginx/fastcgi /var/lib/nginx/logs \
 && chown -R www-data:www-data /tmp/nginx /var/lib/nginx \
 && ln -sf /dev/stdout /var/lib/nginx/logs/access.log \
 && ln -sf /dev/stderr /var/lib/nginx/logs/error.log

# Copy configs
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Override nginx main config to use writable paths
RUN sed -i 's|error_log.*|error_log /var/lib/nginx/logs/error.log warn;|' /etc/nginx/nginx.conf \
 && sed -i 's|pid.*|pid /tmp/nginx.pid;|' /etc/nginx/nginx.conf

# Expose HTTP port
EXPOSE 80

USER www-data

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
