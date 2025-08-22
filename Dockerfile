FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    default-mysql-client

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy existing application directory contents
COPY . /app

# Create basic .env for build process
RUN cp .env.example .env

# Install dependencies first (without running scripts that need artisan)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Generate key and run post-install scripts
RUN php artisan key:generate && composer run-script post-autoload-dump

# Install and build frontend assets
RUN npm install && npm run build

# Create storage link
RUN php artisan storage:link

# Change ownership of our applications
RUN chown -R www-data:www-data /app

# Expose port
EXPOSE 8000

# Start server
CMD php artisan serve --host=0.0.0.0 --port=${PORT}