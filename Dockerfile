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
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application
COPY . /app

# Create .env and set permissions
RUN cp .env.example .env && \
    chmod -R 777 storage bootstrap/cache

# Install dependencies with platform check bypass
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Generate key
RUN php artisan key:generate

# Install frontend dependencies and build
RUN npm install && npm run build

# Create storage link
RUN php artisan storage:link

# Run migrations and start server
CMD php artisan migrate --force && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan serve --host=0.0.0.0 --port=$PORT