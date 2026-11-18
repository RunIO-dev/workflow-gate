FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-progress --no-scripts
COPY . .
RUN composer dump-autoload && php bin/console cache:warmup

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
