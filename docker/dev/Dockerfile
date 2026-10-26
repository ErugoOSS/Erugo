# Development image based on Alpine - matches production architecture
FROM alpine:3.23

LABEL maintainer="Dean Ward"

# Multi-arch support - TARGETARCH is automatically set by Docker buildx
ARG TARGETARCH

WORKDIR /var/www/html

ENV TZ=UTC
ENV SUPERVISOR_PHP_FPM_COMMAND="/usr/sbin/php-fpm85 -F"
ENV SUPERVISOR_CADDY_COMMAND="/usr/sbin/caddy run --config /etc/caddy/Caddyfile"
ENV SUPERVISOR_PHP_USER="erugo"

# Enable community repository for PHP packages
RUN echo "http://dl-cdn.alpinelinux.org/alpine/v3.23/community" >> /etc/apk/repositories

# Install production requirements + development tools
RUN apk add --no-cache \
    curl \
    su-exec \
    sudo \
    supervisor \
    cronie \
    ffmpeg \
    php85 \
    php85-pdo_pgsql \
    php85-pdo_sqlite \
    php85-gd \
    php85-curl \
    php85-pecl-mongodb \
    php85-pecl-imap \
    php85-pdo_mysql \
    php85-mbstring \
    php85-xml \
    php85-zip \
    php85-bcmath \
    php85-soap \
    php85-intl \
    php85-ldap \
    php85-pecl-redis \
    php85-pecl-memcached \
    php85-phar \
    php85-openssl \
    php85-json \
    php85-dom \
    php85-tokenizer \
    php85-fileinfo \
    php85-ctype \
    php85-session \
    php85-simplexml \
    php85-xmlwriter \
    php85-sodium \
    tzdata \
    libcap \
    zip \
    unzip \
    git \
    php85-fpm \
    caddy \
    nodejs \
    npm \
    && ln -s /usr/bin/php85 /usr/bin/php \
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
RUN mkdir -p /run/php /var/www/html/public /etc/php85/php-fpm.d /etc/caddy

COPY ./www.conf /etc/php85/php-fpm.d/www.conf
COPY ./Caddyfile /etc/caddy/Caddyfile

# Set timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure PHP capabilities
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php85

# Create erugo user and group
RUN addgroup erugo && \
    adduser -D -G erugo erugo

# Set permissions
RUN chown -R erugo:erugo /run/php

# Copy configuration files
COPY ./start-container /usr/local/bin/start-container
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./php.ini /etc/php85/conf.d/99-erugo.ini
RUN chmod +x /usr/local/bin/start-container

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Create npm cache directory with correct permissions
RUN mkdir -p /.npm && chown -R erugo:erugo /.npm

EXPOSE 80/tcp
EXPOSE 5173/tcp

ENTRYPOINT ["start-container"]

