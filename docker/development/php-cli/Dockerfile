FROM php:7.1-cli

# Debian Buster снят с зеркал (404 на deb.debian.org). Только archive + без проверки срока Release.
RUN rm -f /etc/apt/sources.list.d/*.list \
    && printf '%s\n' \
        'deb http://archive.debian.org/debian buster main' \
        'deb http://archive.debian.org/debian-security buster/updates main' \
        > /etc/apt/sources.list \
    && printf '%s\n' 'Acquire::Check-Valid-Until "false";' \
        > /etc/apt/apt.conf.d/99no-check-valid-until

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    ca-certificates \
    coreutils \
    curl \
    git \
    unzip \
    build-essential \
    autoconf \
    automake \
    libtool \
    make \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug-2.9.8 \
    && docker-php-ext-enable xdebug

ENV PROTOBUF_VERSION=3.6.1
ENV PROTOBUF_URL=https://github.com/google/protobuf/releases/download/v${PROTOBUF_VERSION}/protobuf-cpp-${PROTOBUF_VERSION}.zip
RUN curl -L -o /tmp/protobuf.zip "$PROTOBUF_URL" \
    && unzip /tmp/protobuf.zip -d /tmp \
    && cd /tmp/protobuf-${PROTOBUF_VERSION} \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && cd / \
    && rm -rf /tmp/protobuf.zip /tmp/protobuf-${PROTOBUF_VERSION}

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY ./common/php/conf.d /usr/local/etc/php/conf.d
COPY ./development/php/conf.d /usr/local/etc/php/conf.d

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --2.2 \
    && rm -rf /root/.composer/cache

RUN groupadd -g 1000 app && useradd -u 1000 -g app -s /bin/sh -m app

WORKDIR /app

USER app
