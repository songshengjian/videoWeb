FROM php:8.2-cli

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 8789

# Start the application
CMD ["php", "start.php", "start"]
