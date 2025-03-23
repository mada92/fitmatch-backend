FROM php:8.2-fpm

# Argumenty używane podczas budowy obrazu
ARG SYMFONY_ENV=dev

# Ustawienie zmiennych środowiskowych
ENV SYMFONY_ENV=${SYMFONY_ENV} \
    APP_ENV=${SYMFONY_ENV} \
    COMPOSER_ALLOW_SUPERUSER=1

# Zainstaluj zależności systemowe
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Skonfiguruj PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    intl

# Zainstaluj Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ustaw uprawnienia do katalogu roboczego
WORKDIR /var/www/html

# Pozostałe instrukcje
CMD ["php-fpm"]