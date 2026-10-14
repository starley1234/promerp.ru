# Образ php-fpm для PromERP (PHP 8.4 + расширения, нужные приложению).
#
# Сборка: docker compose build app
# ВНИМАНИЕ: в песочнице Arena проверка Docker-пути невозможна (реестры образов
# недоступны). Основной, проверенный способ запуска — нативный (см. docs/DEVELOPMENT.md).
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        curl-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql mbstring zip gd intl opcache bcmath sockets

# Xdebug — по желанию: docker compose build --build-arg INSTALL_XDEBUG=1 app
ARG INSTALL_XDEBUG=0
RUN if [ "$INSTALL_XDEBUG" = "1" ]; then \
        apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
        && pecl install xdebug \
        && docker-php-ext-enable xdebug \
        && apk del .build-deps; \
    fi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-promerp.ini

WORKDIR /srv/promerp

# Пользователь приложения (совпадает с www-data в alpine)
RUN addgroup -g 1000 -S promerp 2>/dev/null || true \
    && adduser -u 1000 -S promerp -G promerp 2>/dev/null || true

CMD ["php-fpm"]
