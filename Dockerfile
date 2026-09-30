FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libonig-dev libxml2-dev libzip-dev libsqlite3-dev && docker-php-ext-install pdo_mysql pdo_sqlite mbstring xml zip && a2enmod rewrite && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/nodara.conf && a2enconf nodara
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist && chown -R www-data:www-data storage bootstrap/cache
CMD ["apache2-foreground"]
