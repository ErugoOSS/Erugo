FROM ubuntu:24.04

LABEL maintainer="Dean Ward"

ARG WWWGROUP
ARG NODE_VERSION=22

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y  ca-certificates \
    && apt-get install -y gnupg gosu curl zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano wireguard-tools iproute2 sudo \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y php8.3-cli php8.3-dev \
    php8.3-pgsql php8.3-sqlite3 php8.3-gd \
    php8.3-curl php8.3-mongodb \
    php8.3-imap php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-bcmath php8.3-soap \
    php8.3-intl php8.3-readline \
    php8.3-ldap \
    php8.3-msgpack php8.3-igbinary php8.3-redis \
    php8.3-memcached php8.3-pcov php8.3-imagick php8.3-xdebug php8.3-swoole \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    # && apt-get install -y nodejs \
    # && npm install -g npm \
    && apt-get update \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.3

RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
# Allow sail user to run sudo without password for WireGuard commands
RUN echo "sail ALL=(ALL) NOPASSWD: /usr/bin/wg, /usr/bin/wg-quick, /usr/sbin/ip, /usr/bin/tee, /usr/bin/mkdir, /usr/bin/chmod" >> /etc/sudoers.d/sail

#copy project files and remove .env and .git
COPY . /var/www/html/
RUN rm -rf /var/www/html/.env /var/www/html/.git
RUN ls /var/www/html
# RUN rm -rf /var/www/html/node_modules
# RUN rm /var/www/html/package-lock.json

# Install PHP dependencies
# RUN composer install --no-dev --no-interaction --optimize-autoloader

# Install and build frontend dependencies
# RUN npm install && npm run build

# Fix permissions
RUN chown -R sail:$WWWGROUP /var/www/html

COPY ./start-container /usr/local/bin/start-container
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
