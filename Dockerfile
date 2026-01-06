# Dockerfile para Laravel 12 con PHP 8.2
FROM php:8.2-fpm

# Argumentos para configurar el usuario
ARG user=laravel
ARG uid=1000

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

# Crear usuario del sistema para ejecutar comandos Composer y Artisan
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Configurar directorio de trabajo
WORKDIR /var/www

# Copiar el código de la aplicación primero
COPY --chown=$user:$user . .

# Crear directorios necesarios con permisos correctos
RUN mkdir -p /var/www/vendor /var/www/storage /var/www/bootstrap/cache && \
    chown -R $user:www-data /var/www && \
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Instalar dependencias de PHP como usuario laravel
USER $user
RUN composer install --optimize-autoloader --no-interaction

# Exponer puerto 9000 y ejecutar php-fpm
EXPOSE 9000
CMD ["php-fpm"]
