FROM php:8.4-fpm

# Add custom php.ini file
COPY ./docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Install system dependencies and PHP extensions in a single layer
RUN apt-get update && apt-get install -y \
    nano \
    nginx \
    git \
    unzip \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    supervisor \
    gnupg2 \
    ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql mbstring zip exif pcntl gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get update && apt-get install -y nodejs
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel app source
COPY . .

# Create .env file and set proper permissions
RUN if [ -f .env.example ]; then cp .env.example .env; else touch .env; fi \
    && chown www-data:www-data .env \
    && chmod 664 .env

# Change ownership of the entire application directory to the www-data user
RUN chown -R www-data:www-data /var/www

# Prepare Laravel cache paths & permissions
# RUN mkdir -p storage/framework/{views,sessions,cache} \
#     && mkdir -p bootstrap/cache \
#     && chown -R www-data:www-data storage bootstrap/cache \
#     && chmod -R 775 storage bootstrap/cache

RUN mkdir -p storage/framework/{views,sessions,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data storage/framework storage/logs bootstrap/cache \
    && chmod -R 775 storage/framework storage/logs bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader && php artisan wayfinder:generate

# Install npm dependencies and build assets
RUN npm install && npm run build

# Laravel Artisan commands

RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan config:cache \
    && php artisan view:cache \
    && php artisan optimize:clear

# Configure Nginx and Supervisor
RUN rm -f /etc/nginx/sites-enabled/default
COPY ./docker/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose HTTP port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

# Start all services via supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
