FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    icu-dev \
    linux-headers

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        gd \
        exif \
        bcmath \
        pcntl \
        zip \
        intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for larger uploads and reasonable timeouts
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/backend

# Copy entire backend source code
COPY backend/ .

# Ensure .env exists, install dependencies, and set permissions
RUN cp -n .env.example .env 2>/dev/null || true \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && chmod -R 777 /var/www/backend/storage /var/www/backend/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
