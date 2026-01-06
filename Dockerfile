# Dockerfile para Laravel 12 con PHP 8.2
FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# Limpiar cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www

# Copiar archivos de composer primero para aprovechar cache de Docker
COPY composer.json composer.lock ./

# Instalar dependencias de PHP
RUN composer install --optimize-autoloader --no-interaction

# Copiar el resto del código
COPY . .

# Configurar permisos - esto se ejecutará cada vez que inicie el contenedor
RUN chmod -R 777 storage bootstrap/cache || true

# Crear script de inicio
RUN echo '#!/bin/bash\n\
chmod -R 777 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true\n\
php-fpm' > /usr/local/bin/start.sh && \
chmod +x /usr/local/bin/start.sh

# Exponer puerto 9000 y ejecutar php-fpm
EXPOSE 9000
CMD ["/usr/local/bin/start.sh"]
