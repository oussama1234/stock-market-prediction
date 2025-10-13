FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies including Python
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    wget \
    supervisor \
    python3 \
    python3-pip \
    python3-dev \
    python3-venv \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Create symlink for python command
RUN ln -s /usr/bin/python3 /usr/bin/python

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application code
COPY --chown=www-data:www-data ./backend /var/www/html

# Install Python dependencies for ML model
RUN if [ -f /var/www/html/python/requirements.txt ]; then \
        pip3 install --no-cache-dir --break-system-packages -r /var/www/html/python/requirements.txt; \
    fi

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Install composer dependencies (ignore errors during build)
RUN cd /var/www/html && composer install --optimize-autoloader --no-interaction --no-dev || true

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
