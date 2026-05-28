FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        rabbitmq-c-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql sockets opcache \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist

COPY . .

RUN chown -R www-data:www-data var/

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
