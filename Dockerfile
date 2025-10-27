FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Set Apache DocumentRoot to Laravel's public directory
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# Copy project files
COPY . /var/www/html

WORKDIR /var/www/html

# Fix permissions for Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 80
