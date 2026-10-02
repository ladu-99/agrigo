# AgriConnect - Docker runtime
# Apache + PHP preserves the app's existing relative paths
# (../../backend/api/..., ../../backend/uploads/...) and .htaccess rewrites,
# because the document root is the repository root.
FROM php:8.2-apache

# --- PHP extensions -------------------------------------------------------
# pdo_mysql is required by backend/config/database.php
RUN docker-php-ext-install pdo_mysql

# --- Apache modules -------------------------------------------------------
RUN a2enmod rewrite headers expires

# --- App configuration ----------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/agriconnect.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/agriconnect-entrypoint.sh
RUN chmod +x /usr/local/bin/agriconnect-entrypoint.sh

# --- Application source ---------------------------------------------------
# The whole project (frontend + backend + database) becomes the document root
# at /var/www/html.
WORKDIR /var/www/html
COPY . /var/www/html/

# Ensure upload destinations exist and are writable by the web user.
RUN mkdir -p backend/uploads/resources backend/uploads/images \
             backend/uploads/documents backend/uploads/products \
    && chown -R www-data:www-data backend/uploads \
    && find backend/uploads -type d -exec chmod 775 {} \;

# The entrypoint rewrites Apache's Listen/VirtualHost directives to use $PORT.
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/agriconnect-entrypoint.sh"]
CMD ["apache2-foreground"]
