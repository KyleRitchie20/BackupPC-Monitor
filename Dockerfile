# Multi-stage build to properly install tokenizer extension

# Stage 1: Build tokenizer extension in a complete environment
FROM php:8.2-cli as tokenizer-builder

# Install build dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libpq-dev \
    libsqlite3-dev \
    git \
    curl \
    unzip \
    zip \
    && docker-php-ext-install tokenizer

# Stage 2: Final image with application
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    postgresql-client \
    libpq-dev \
    sqlite3 \
    bash \
    nano \
    nodejs \
    npm

# Copy tokenizer extension from builder stage
COPY --from=tokenizer-builder /usr/local/lib/php/extensions/no-debug-non-zts-20220829/tokenizer.so /usr/local/lib/php/extensions/no-debug-non-zts-20220829/tokenizer.so

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql gd xml mbstring ctype json xmlreader xmlwriter opcache

# Install Node.js and npm
RUN apt-get install -y npm \
    && npm install -g npm

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --optimize-autoloader --no-dev --no-interaction

# Copy application code
COPY . .

# Install NPM dependencies and build assets
RUN npm install && npm run build

# Create storage directories and set permissions
RUN mkdir -p storage/framework/cache \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

# Expose port 8000
EXPOSE 8000

# Create a non-root user for security
RUN addgroup -g 1000 -S www-data && \
    adduser -u 1000 -S www-data -g www-data

# Switch to non-root user
USER www-data

# Start Laravel development server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]