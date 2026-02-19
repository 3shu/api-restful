FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    unixodbc-dev \
    gnupg2 \
    wget \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy PHP-FPM configuration
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/symfony

# PHP configuration for development
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && echo "memory_limit = 512M" >> "$PHP_INI_DIR/php.ini" \
    && echo "upload_max_filesize = 50M" >> "$PHP_INI_DIR/php.ini" \
    && echo "post_max_size = 50M" >> "$PHP_INI_DIR/php.ini"

# Create user for the application
RUN useradd -G www-data,root -u 1000 -d /home/symfony symfony \
    && mkdir -p /home/symfony/.composer \
    && chown -R symfony:symfony /home/symfony

# Switch back to root to run PHP-FPM (required for port binding)
# PHP-FPM will run as www-data user internally
USER root

EXPOSE 9000

CMD ["php-fpm"]
