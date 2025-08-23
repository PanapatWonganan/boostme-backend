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

# Configure PHP for large file uploads
RUN echo "upload_max_filesize = 500M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 500M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

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

# Force HTTPS in production
ENV APP_URL=https://boostme-backend-production.up.railway.app
ENV ASSET_URL=https://boostme-backend-production.up.railway.app

# Run migrations and create admin user if not exists
CMD php artisan migrate --force && \
    php artisan tinker --execute="if(!\\App\\Models\\User::where('email', 'admin@boostme.com')->exists()) { \\App\\Models\\User::create(['id' => Str::uuid(), 'email' => 'admin@boostme.com', 'password_hash' => Hash::make('Admin123!'), 'full_name' => 'Admin', 'role' => 'admin', 'email_verified' => true]); }" && \
    php artisan config:clear && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan serve --host=0.0.0.0 --port=$PORT