FROM php:8.3-fpm AS php-build

RUN apt-get update && apt-get install -y  \
    libzip-dev git libmagickwand-dev libzip-dev \
    --no-install-recommends \
    && docker-php-ext-install pdo_mysql zip exif && \
    git clone https://github.com/Imagick/imagick.git --depth 1 /tmp/imagick && \
    cd /tmp/imagick && \
    git fetch origin master && \
    git switch master && \
    cd /tmp/imagick && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    pecl install pcov && \
    docker-php-ext-enable pcov


# Final stage
FROM php:8.3-fpm

# Copy PHP extensions and configurations from build stage
COPY --from=php-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install needed libraries and tools
RUN apt-get update && apt-get install -y \
    libmagickwand-dev \
    libzip-dev \
    wkhtmltopdf \
    --no-install-recommends \
    && docker-php-ext-enable imagick pcov \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
# Get NodeJS
COPY --from=node:20-slim /usr/local/bin /usr/local/bin
# Get npm
COPY --from=node:20-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

# Install browser based on architecture
RUN apt-get update && apt-get install -y \
    wget \
    gnupg \
    ca-certificates \
    apt-transport-https

# Check architecture and install appropriate browser
RUN case $(uname -m) in \
    x86_64) \
        wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add - && \
        echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google-chrome.list && \
        apt-get update && \
        apt-get install -y google-chrome-stable \
        ;; \
    aarch64|arm64) \
        apt-get update && \
        apt-get install -y chromium \
        ;; \
    *) \
        echo "Unsupported architecture: $(uname -m)" && \
        exit 1 \
        ;; \
esac

WORKDIR /var/www

RUN php -v && composer --version && node -v && npm -v
