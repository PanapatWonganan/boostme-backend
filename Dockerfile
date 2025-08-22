FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    nodejs \
    npm \
    default-mysql-client

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy existing application directory contents
COPY . /app

# Create basic .env for build process
RUN cp .env.example .env

# Install dependencies (ignore platform reqs and update if needed)
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs || composer update --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Generate key 
RUN php artisan key:generate

# Install and build frontend assets
RUN npm install && npm run build

# Create storage link
RUN php artisan storage:link

# Change ownership of our applications
RUN chown -R www-data:www-data /app

# Expose port (Railway typically uses 8080)
EXPOSE 8080

# Run migrations during build
RUN php artisan migrate --force || true

# Start server - Railway will set PORT env var
CMD ["sh", "-c", "echo 'Starting Laravel on port:' ${PORT:-8080} && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]