FROM php:8.0-fpm AS php-build

RUN apt-get update && apt-get install -y  \
    libzip-dev \
    --no-install-recommends \
    && docker-php-ext-install pdo_mysql zip exif


# Final stage
FROM php:8.0-fpm

# Copy PHP extensions and configurations from build stage
COPY --from=php-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN apt-get update && apt-get install -y \
    libmagickwand-dev \
    libzip-dev \
    --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
# Get NodeJS
COPY --from=node:20-slim /usr/local/bin /usr/local/bin
# Get npm
COPY --from=node:20-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

WORKDIR /var/www

RUN php -v && composer --version && node -v && npm -v

