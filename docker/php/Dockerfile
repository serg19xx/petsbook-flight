FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Добавить в Dockerfile:      
RUN mkdir -p /var/www/html/public/profile-images/avatars/
RUN mkdir -p /var/www/html/public/profile-images/covers/
RUN chmod -R 777 /var/www/html/public/profile-images/avatars/
RUN chmod -R 777 /var/www/html/public/profile-images/covers/

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Note: we removed COPY and composer install commands
