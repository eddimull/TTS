# Use serversideup FrankenPHP image - combines web server + PHP in one
FROM serversideup/php:8.3-frankenphp AS base

# Switch to root to install additional packages
USER root

# Disable IPv6 for apt to fix connection issues
RUN echo 'Acquire::ForceIPv4 "true";' > /etc/apt/apt.conf.d/99force-ipv4

# Install additional dependencies including FFmpeg for video processing
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    git \
    wget \
    gnupg \
    ca-certificates \
    apt-transport-https \
    ffmpeg \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Install additional PHP extensions
# FrankenPHP images come with most common extensions pre-installed
RUN install-php-extensions imagick pcov gd

# Copy custom PHP configuration
COPY ./docker/uploads.ini /usr/local/etc/php/conf.d/

# Copy MySQL client configuration
RUN mkdir -p /etc/mysql/conf.d
COPY ./docker/mysql-client.cnf /etc/mysql/conf.d/

# Copy Caddyfile and SSL certificates for FrankenPHP
RUN mkdir -p /etc/frankenphp /data/caddy/ssl
COPY ./docker/Caddyfile /etc/frankenphp/Caddyfile

# Copy SSL certificates and set correct ownership for www-data
COPY ./ssl/sail-selfsigned.crt /data/caddy/ssl/cert.crt
COPY ./ssl/sail-selfsigned.key /data/caddy/ssl/cert.key
RUN chown -R www-data:www-data /data/caddy/ssl && chmod 600 /data/caddy/ssl/cert.key

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install Node.js and npm
COPY --from=node:20-slim /usr/local/bin /usr/local/bin
COPY --from=node:20-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

# Install Chrome (x86_64) or Chromium (ARM) for Dusk testing
RUN case $(uname -m) in \
        x86_64) \
            echo "Installing Chrome for x86_64..." && \
            wget --timeout=30 --tries=3 -q -O /usr/share/keyrings/google-chrome-keyring.gpg https://dl-ssl.google.com/linux/linux_signing_key.pub && \
            echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome-keyring.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list && \
            apt-get update && \
            apt-get install -y --no-install-recommends google-chrome-stable && \
            echo "Chrome installed successfully" \
            ;; \
        aarch64|arm64) \
            echo "Installing Chromium for ARM..." && \
            apt-get update && \
            apt-get install -y --no-install-recommends chromium && \
            echo "Chromium installed successfully" \
            ;; \
    esac \
    && rm -rf /var/lib/apt/lists/*

# Set working directory back to /var/www (Laravel root)
WORKDIR /var/www

# Change www-data UID/GID to match host user (1000) to fix file permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data && \
    chown -R www-data:www-data /data/caddy

# Switch back to www-data user for security
USER www-data

# Verify installations
RUN php -v && composer --version && node -v && npm -v