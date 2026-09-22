# =============================================================================
#  Imagen del Servicio de Departamentos (PHP 8.3 + Apache)
#  Construcción multi-etapa: las dependencias se resuelven en una etapa aparte
#  para que la imagen final no contenga Composer ni cachés de compilación.
# =============================================================================

# ---------- Etapa 1: dependencias -------------------------------------------
FROM composer:2 AS dependencias

WORKDIR /app

COPY composer.* ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# ---------- Etapa 2: imagen de ejecución ------------------------------------
FROM php:8.3-apache AS runtime

LABEL org.opencontainers.image.title="servicio-departamentos" \
      org.opencontainers.image.description="Microservicio de gestión de departamentos" \
      org.opencontainers.image.vendor="Equipo de Desarrollo"

RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini    /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

COPY --from=dependencias /app/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=5 \
    CMD php -r "exit(@file_get_contents('http://localhost/salud') ? 0 : 1);"
