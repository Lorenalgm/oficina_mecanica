FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql zip mbstring bcmath \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && printf "pcov.enabled = 1\npcov.directory = /var/www\npcov.exclude = \"~vendor~\"\nopcache.enable_cli = 0\n" > /usr/local/etc/php/conf.d/99-pcov.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --optimize-autoloader

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
