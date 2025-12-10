# Development image based on Alpine - matches production architecture
FROM alpine:3.19

LABEL maintainer="Dean Ward"

# Multi-arch support - TARGETARCH is automatically set by Docker buildx
ARG TARGETARCH

WORKDIR /var/www/html

ENV TZ=UTC
ENV SUPERVISOR_PHP_FPM_COMMAND="/usr/sbin/php-fpm83 -F"
ENV SUPERVISOR_CADDY_COMMAND="/usr/sbin/caddy run --config /etc/caddy/Caddyfile"
ENV SUPERVISOR_PHP_USER="erugo"

# Enable community repository for PHP packages
RUN echo "http://dl-cdn.alpinelinux.org/alpine/v3.19/community" >> /etc/apk/repositories

# Install production requirements + development tools
RUN apk add --no-cache \
    curl \
    su-exec \
    sudo \
    supervisor \
    cronie \
    ffmpeg \
    php83 \
    php83-pdo_pgsql \
    php83-pdo_sqlite \
    php83-gd \
    php83-curl \
    php83-pecl-mongodb \
    php83-imap \
    php83-pdo_mysql \
    php83-mbstring \
    php83-xml \
    php83-zip \
    php83-bcmath \
    php83-soap \
    php83-intl \
    php83-ldap \
    php83-pecl-redis \
    php83-pecl-memcached \
    php83-phar \
    php83-openssl \
    php83-json \
    php83-dom \
    php83-tokenizer \
    php83-fileinfo \
    php83-ctype \
    php83-session \
    php83-simplexml \
    php83-xmlwriter \
    php83-sodium \
    tzdata \
    libcap \
    zip \
    unzip \
    git \
    php83-fpm \
    caddy \
    nodejs \
    npm \
    && ln -s /usr/bin/php83 /usr/bin/php \
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
RUN mkdir -p /run/php /var/www/html/public /etc/php83/php-fpm.d /etc/caddy

COPY ./www.conf /etc/php83/php-fpm.d/www.conf
COPY ./Caddyfile /etc/caddy/Caddyfile

# Set timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure PHP capabilities
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php83

# Create erugo user and group
RUN addgroup erugo && \
    adduser -D -G erugo erugo

# Set permissions
RUN chown -R erugo:erugo /run/php

# Copy configuration files
COPY ./start-container /usr/local/bin/start-container
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./php.ini /etc/php83/conf.d/99-erugo.ini
RUN chmod +x /usr/local/bin/start-container

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Create npm cache directory with correct permissions
RUN mkdir -p /.npm && chown -R erugo:erugo /.npm

EXPOSE 80/tcp
EXPOSE 5173/tcp

ENTRYPOINT ["start-container"]

