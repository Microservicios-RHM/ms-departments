FROM php:8.3-cli-alpine

# Imagen reproducible para las etapas Build y Test del pipeline. Xdebug solo
# existe en CI: no forma parte de la imagen de ejecucion del microservicio.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers
