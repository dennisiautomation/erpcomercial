# syntax=docker/dockerfile:1.6
# Imagem de produção — PHP-FPM + Nginx + Supervisor no mesmo container.
# Isso mantém queue:work e schedule:work rodando sem precisar de outro serviço.

FROM php:8.4-fpm-alpine AS base

# Extensões necessárias para Laravel 12 + MySQL + Redis + imagick (etiquetas)
RUN apk add --no-cache \
        bash curl git unzip icu-dev libzip-dev oniguruma-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        nginx supervisor tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mysqli bcmath intl zip pcntl gd opcache exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && cp /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime \
    && echo "America/Sao_Paulo" > /etc/timezone \
    && apk del tzdata

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php.ini com OPcache on
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-erp.ini

WORKDIR /var/www

# Dependências primeiro (cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Código
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Nginx + supervisor + entrypoint
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/supervisor /run/nginx

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
