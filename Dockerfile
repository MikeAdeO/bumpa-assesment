FROM php:8.2-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

COPY . .

RUN chmod +x docker-entrypoint.sh \
    && php artisan package:discover --ansi

EXPOSE 8000

# Invoked via `sh` rather than executed directly: docker-compose.yml
# bind-mounts the repo over this image at runtime (`.:/var/www/html`), so
# the container sees the host's copy of this file — and git clones don't
# reliably preserve the executable bit `chmod +x` set above. Running it
# through the shell explicitly means a fresh `git clone` works regardless
# of what permission bit it landed with on disk.
ENTRYPOINT ["/bin/sh", "docker-entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]