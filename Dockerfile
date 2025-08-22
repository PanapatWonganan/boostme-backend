FROM webdevops/php-nginx:8.2-alpine

# Set working directory
WORKDIR /app

# Install additional PHP extensions if needed
RUN apk add --no-cache mysql-client nodejs npm

# Copy application
COPY . /app

# Set permissions
RUN chown -R application:application /app

# Create .env file
RUN cp .env.example .env

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Generate key
RUN php artisan key:generate

# Install and build frontend
RUN npm install && npm run build

# Create storage link
RUN php artisan storage:link

# Set correct permissions for Laravel
RUN chown -R application:application /app/storage /app/bootstrap/cache
RUN chmod -R 775 /app/storage /app/bootstrap/cache

# Configure nginx
ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_MEMORY_LIMIT=512M
ENV PHP_MAX_EXECUTION_TIME=60
ENV PHP_POST_MAX_SIZE=50M
ENV PHP_UPLOAD_MAX_FILESIZE=50M

# Expose port
EXPOSE 80

# The base image already has proper entrypoint for nginx+php-fpm