#!/bin/bash

# Script de despliegue para actualizar la aplicación desde Git

set -e

echo "🚀 Iniciando despliegue..."

# 1. Obtener últimos cambios de Git
echo "📥 Descargando cambios desde Git..."
git pull origin main

# 2. Reconstruir la imagen de Docker si hay cambios en Dockerfile o composer.json
echo "🔨 Reconstruyendo contenedores..."
docker-compose build --no-cache app

# 3. Detener contenedores
echo "🛑 Deteniendo contenedores..."
docker-compose down

# 4. Iniciar contenedores
echo "▶️  Iniciando contenedores..."
docker-compose up -d

# 5. Esperar a que los contenedores estén listos
echo "⏳ Esperando a que los contenedores estén listos..."
sleep 5

# 6. Instalar/actualizar dependencias
echo "📦 Instalando dependencias..."
docker-compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction

# 7. Ejecutar migraciones
echo "🗄️  Ejecutando migraciones..."
docker-compose exec -T app php artisan migrate --force

# 8. Limpiar y optimizar cache
echo "🧹 Limpiando caché..."
docker-compose exec -T app php artisan optimize:clear
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

# 9. Reiniciar servicios
echo "🔄 Reiniciando servicios..."
docker-compose restart app

echo "✅ Despliegue completado exitosamente!"
echo "📊 Estado de los contenedores:"
docker-compose ps
