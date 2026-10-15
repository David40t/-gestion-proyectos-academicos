# Imagen única de la aplicación: Apache + PHP. Se reutiliza para la web, el worker de colas y el scheduler.
FROM php:8.4-apache

# Extensiones requeridas (README §3). mbstring, openssl, fileinfo, tokenizer y pdo_sqlite ya vienen en la imagen.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl bcmath zip opcache \
    && a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/servername.conf /etc/apache2/conf-enabled/servername.conf
COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

WORKDIR /var/www/html

# true: incluye las dependencias de desarrollo (necesarias para `php artisan test`). En producción: false.
ARG INSTALL_DEV=true

# Dependencias primero para aprovechar la caché de capas de Docker.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader \
        $( [ "$INSTALL_DEV" = "true" ] || echo --no-dev )

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && ln -s storage/app/docker/.env .env \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
