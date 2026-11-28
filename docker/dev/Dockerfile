# Development image based on Alpine - matches production architecture
FROM alpine:3.24

LABEL maintainer="Dean Ward"

# Multi-arch support - TARGETARCH is automatically set by Docker buildx
ARG TARGETARCH

WORKDIR /var/www/html

ENV TZ=UTC
ENV SUPERVISOR_PHP_FPM_COMMAND="/usr/sbin/php-fpm84 -F"
ENV SUPERVISOR_CADDY_COMMAND="/usr/sbin/caddy run --config /etc/caddy/Caddyfile"
ENV SUPERVISOR_PHP_USER="erugo"

# The php84 -r check at the end of this step fails the build unless PHP is
# 8.4.27 or later on the 8.4 branch (fixes CVE-2026-109618 and CVE-2026-109619).
# Install production requirements + development tools
RUN apk add --no-cache \
    curl \
    su-exec \
    sudo \
    supervisor \
    cronie \
    ffmpeg \
    poppler-utils \
    imagemagick \
    librsvg \
    libheif \
    exiftool \
    php84 \
    php84-pdo_pgsql \
    php84-pdo_sqlite \
    php84-gd \
    php84-curl \
    php84-pecl-mongodb \
    php84-pecl-imap \
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
    php84-pecl-imagick \
    php84-ctype \
    php84-session \
    php84-simplexml \
    php84-xmlwriter \
    php84-sodium \
    tzdata \
    libcap \
    zip \
    unzip \
    git \
    php84-fpm \
    caddy \
    nodejs \
    npm \
    && php84 -r 'exit(PHP_VERSION_ID >= 80427 && PHP_VERSION_ID < 80500 ? 0 : 1);' \
    && ln -sf /usr/bin/php84 /usr/bin/php \
    && rm -rf /var/cache/apk/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Download and install tusd for the correct architecture
ENV TUSD_VERSION=2.6.0
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

COPY ./www.conf /etc/php84/php-fpm.d/www.conf
COPY ./Caddyfile /etc/caddy/Caddyfile

# Set timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure PHP capabilities
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php84

# Create erugo user and group
RUN addgroup erugo && \
    adduser -D -G erugo erugo

# Set permissions
RUN chown -R erugo:erugo /run/php

# Copy configuration files
COPY ./start-container /usr/local/bin/start-container
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./php.ini /etc/php84/conf.d/99-erugo.ini
RUN chmod +x /usr/local/bin/start-container

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Create npm cache directory with correct permissions
RUN mkdir -p /.npm && chown -R erugo:erugo /.npm

EXPOSE 80/tcp
EXPOSE 5173/tcp

ENTRYPOINT ["start-container"]

