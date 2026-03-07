FROM php:8.2-apache

# Tools often required by Composer in CI/build environments.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip \
	&& rm -rf /var/lib/apt/lists/*

# PHP extensions needed by Taskora
RUN docker-php-ext-install pdo pdo_mysql

# Apache modules
RUN a2enmod rewrite

# Composer for PHP dependencies (phpdotenv)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html
COPY . /var/www/html

# Install dependencies for production
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Keep runtime writable where needed
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
