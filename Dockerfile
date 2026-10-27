# Production image for the TrainingHub Laravel API.
FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=10000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip gd bcmath dom \
    && a2enmod rewrite headers \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache public/uploads/banners \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads \
    && chmod -R ug+rwX storage bootstrap/cache public/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["entrypoint.sh"]
