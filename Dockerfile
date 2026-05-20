FROM composer:2 AS builder
WORKDIR /app

# Install PHP deps via Composer in builder stage
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy app sources (excluding files in .dockerignore)
COPY . .
RUN composer dump-autoload --optimize

FROM php:8.2-apache

# System packages and PHP extensions (SQLite used by this app)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libsqlite3-dev libzip-dev default-libmysqlclient-dev unzip \
    && docker-php-ext-install pdo_sqlite pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Copy application from builder
COPY --from=builder /app /var/www/html

# Serve the `public` directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot ${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!<Directory /var/www/>!<Directory ${APACHE_DOCUMENT_ROOT}>!g' /etc/apache2/apache2.conf

# Set permissions for the web server
RUN chown -R www-data:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} + \
 && find /var/www/html -type f -exec chmod 644 {} +

EXPOSE 80

CMD ["apache2-foreground"]
