# Build Stage for PHP dependencies
FROM php:8.4-cli-alpine AS composer
WORKDIR /app
RUN apk add --no-cache git unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY . .
RUN composer install --no-interaction --optimize-autoloader

# Build Stage for Node.js dependencies and assets
FROM node:20-alpine AS node
WORKDIR /app
COPY . .

RUN npm ci && \
    npx vite build \
    && rm -rf node_modules

# Final production image
FROM alpine:3.24

LABEL maintainer="Dean Ward"

# Multi-arch support - TARGETARCH is automatically set by Docker buildx
ARG TARGETARCH

# Version passed from build system (e.g., Komodo)
ARG VERSION=unknown
ENV APP_VERSION=${VERSION}

WORKDIR /var/www/html

ENV TZ=UTC
ENV SUPERVISOR_PHP_FPM_COMMAND="/usr/sbin/php-fpm84 -F"
ENV SUPERVISOR_CADDY_COMMAND="/usr/sbin/caddy run --config /etc/caddy/Caddyfile"
ENV SUPERVISOR_PHP_USER="erugo"

# Enable community repository for PHP packages
RUN echo "http://dl-cdn.alpinelinux.org/alpine/v3.24/community" >> /etc/apk/repositories

# Install production requirements.
# Alpine builds PHP's core extensions as shared modules, so every extension the
# application loads has to be listed here explicitly — including the ones
# Laravel requires at runtime (ctype, session) which are not pulled in by the
# meta packages.
RUN apk add --no-cache \
    curl \
    su-exec \
    sudo \
    supervisor \
    cronie \
    ffmpeg \
    php84 \
    php84-ctype \
    php84-session \
    php84-iconv \
    php84-posix \
    php84-simplexml \
    php84-xmlreader \
    php84-xmlwriter \
    php84-sodium \
    php84-pdo_pgsql \
    php84-pdo_sqlite \
    php84-gd \
    php84-curl \
    php84-pecl-mongodb \
    php84-pdo_mysql \
    php84-mbstring \
    php84-xml \
    php84-zip \
    php84-bcmath \
    php84-soap \
    php84-intl \
    php84-ldap \
    php84-pecl-redis \
    php84-pecl-memcached \
    php84-phar \
    php84-openssl \
    php84-dom \
    php84-tokenizer \
    php84-fileinfo \
    tzdata \
    libcap \
    zip \
    php84-fpm \
    caddy \
    && ln -s /usr/bin/php84 /usr/bin/php \
    && rm -rf /var/cache/apk/*

# Download and install tusd for the correct architecture
ENV TUSD_VERSION=2.10.1
RUN TUSD_ARCH="${TARGETARCH}" && \
    if [ "$TUSD_ARCH" = "amd64" ]; then TUSD_ARCH="amd64"; \
    elif [ "$TUSD_ARCH" = "arm64" ]; then TUSD_ARCH="arm64"; \
    else echo "Unsupported architecture: $TARGETARCH" && exit 1; fi && \
    curl -L -o /tmp/tusd.tar.gz "https://github.com/tus/tusd/releases/download/v${TUSD_VERSION}/tusd_linux_${TUSD_ARCH}.tar.gz" && \
    tar -xzf /tmp/tusd.tar.gz -C /tmp && \
    mv /tmp/tusd_linux_${TUSD_ARCH}/tusd /usr/local/bin/tusd && \
    chmod +x /usr/local/bin/tusd && \
    rm -rf /tmp/tusd.tar.gz /tmp/tusd_linux_${TUSD_ARCH}

# Create necessary directories
RUN mkdir -p /run/php /var/www/html/public /etc/php84/php-fpm.d /etc/caddy

COPY ./docker/alpine/www.conf /etc/php84/php-fpm.d/www.conf
COPY ./docker/alpine/Caddyfile /etc/caddy/Caddyfile

# Set timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure PHP capabilities
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php84

# Create erugo user and group
RUN addgroup erugo && \
    adduser -D -G erugo erugo

# Set permissions
RUN chown -R erugo:erugo /run/php

# Copy necessary files from build stages and local context
COPY --chown=erugo:erugo . /var/www/html/
COPY --from=composer /app/vendor /var/www/html/vendor
COPY --from=node /app/public/build /var/www/html/public/build
RUN rm -rf /var/www/html/.env /var/www/html/.git /var/www/html/node_modules /var/www/html/storage

# Set up Laravel scheduler cron job
RUN echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" | crontab -u erugo -

# Copy configuration files
COPY ./docker/alpine/start-container /usr/local/bin/start-container
COPY ./docker/alpine/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./docker/alpine/php.ini /etc/php84/conf.d/99-erugo.ini
RUN chmod +x /usr/local/bin/start-container

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]