# Docker Setup - Datasefil API

## Requisitos previos

- Docker
- Docker Compose
- Git

## Modos de ejecución

Este proyecto soporta dos modos:
- **Desarrollo**: Los cambios en el código se reflejan automáticamente (usa `docker-compose.yml`)
- **Producción**: Código embebido en la imagen (usa `docker-compose.prod.yml`)

## Instalación y configuración

### 1. Configurar variables de entorno

Copia el archivo `.env.example` a `.env` y ajusta las variables de base de datos:

```bash
cp .env.example .env
```

Asegúrate de configurar las siguientes variables en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=datasefil
DB_USERNAME=datasefil_user
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 2. Construir los contenedores

```bash
docker-compose build
```

### 3. Iniciar los contenedores

```bash
docker-compose up -d
```

### 4. Instalar dependencias

```bash
docker-compose exec app composer install
```

### 5. Generar la clave de la aplicación

```bash
docker-compose exec app php artisan key:generate
```

### 6. Ejecutar migraciones

```bash
docker-compose exec app php artisan migrate
```

### 7. Ejecutar seeders (opcional)

```bash
docker-compose exec app php artisan db:seed
```

## Actualización desde Git (Desarrollo)

Cuando hagas `git pull origin main`, los cambios se reflejarán automáticamente porque el código está montado como volumen.

### Opción 1: Script automático (Recomendado)

**Windows:**
```bash
scripts\update-dev.bat
```

**Linux/Mac:**
```bash
chmod +x scripts/update-dev.sh
./scripts/update-dev.sh
```

Este script automáticamente:
- Descarga los últimos cambios de Git
- Actualiza dependencias de Composer si es necesario
- Ejecuta migraciones nuevas
- Limpia el caché

### Opción 2: Actualización manual

```bash
# 1. Descargar cambios
git pull origin main

# 2. Si hay cambios en composer.json
docker-compose exec app composer install

# 3. Si hay nuevas migraciones
docker-compose exec app php artisan migrate

# 4. Limpiar caché
docker-compose exec app php artisan optimize:clear
```

## Despliegue completo (con reconstrucción)

Si hay cambios en Dockerfile o necesitas reconstruir completamente:

**Windows:**
```bash
scripts\deploy.bat
```

**Linux/Mac:**
```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh
```

## Producción

Para producción, usa el archivo de composición específico:

```bash
# Construir e iniciar
docker-compose -f docker-compose.prod.yml up -d --build

# Actualizar
git pull origin main
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml up -d
```

## Comandos útiles

### Acceder al contenedor de la aplicación

```bash
docker-compose exec app bash
```

### Ver logs

```bash
docker-compose logs -f app
```

### Detener los contenedores

```bash
docker-compose down
```

### Detener y eliminar volúmenes (⚠️ borra la base de datos)

```bash
docker-compose down -v
```

### Ejecutar comandos Artisan

```bash
docker-compose exec app php artisan [comando]
```

### Ejecutar Composer

```bash
docker-compose exec app composer [comando]
```

### Limpiar cache

```bash
docker-compose exec app php artisan optimize:clear
```

### Ejecutar tests

```bash
docker-compose exec app php artisan test
```

## Acceso a la aplicación

- **API**: http://localhost:8000
- **Base de datos MySQL**: localhost:3306
- **Redis**: localhost:6379

## Estructura de servicios

- **app**: Aplicación Laravel con PHP 8.2-FPM
- **nginx**: Servidor web Nginx
- **db**: Base de datos MySQL 8.0
- **redis**: Cache y queue manager

## Troubleshooting

### Problemas de permisos en storage/logs

```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R laravel:www-data storage bootstrap/cache
```

### Resetear la base de datos

```bash
docker-compose exec app php artisan migrate:fresh --seed
```

### Ver configuración actual

```bash
docker-compose exec app php artisan config:show
```
