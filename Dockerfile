FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    nginx \
    gnupg

# Install Node.js (for Vite/Tailwind build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Create necessary directories and set permissions BEFORE composer/npm
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Install PHP dependencies
RUN composer install --no-interaction --no-scripts --optimize-autoloader --no-dev

# Install Node dependencies and build assets
RUN npm install && npm run build

# Setup Nginx
COPY .agent/nginx.conf /etc/nginx/sites-available/default

# Generate SSL and final permission check
RUN mkdir -p /etc/nginx/ssl && \
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/nginx.key -out /etc/nginx/ssl/nginx.crt \
    -subj "/C=ID/ST=West Java/L=Cikarang/O=Peroniks/OU=IT/CN=10.88.8.46" && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80 443

CMD ["sh", "-c", "php artisan package:discover --ansi && php artisan storage:link --force && service nginx start && php-fpm"]
