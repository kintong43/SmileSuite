FROM php:8.4-apache-bookworm AS backend
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev libonig-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl mbstring zip bcmath pcntl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/smilesuite.ini

FROM node:24-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.* ./
COPY --from=backend /var/www/html/vendor ./vendor
RUN npm run build

FROM backend AS application
COPY --from=assets /build/public/build ./public/build
EXPOSE 80
CMD ["apache2-foreground"]
