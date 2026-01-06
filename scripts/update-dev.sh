#!/bin/bash

# Script rápido para actualizar en desarrollo (sin reconstruir)

set -e

echo "🔄 Actualizando aplicación en modo desarrollo..."

# 1. Obtener últimos cambios de Git
echo "📥 Descargando cambios desde Git..."
git pull origin main

# 2. Actualizar dependencias si composer.json cambió
if git diff HEAD@{1} --name-only | grep -q "composer.json\|composer.lock"; then
    echo "📦 Detectados cambios en composer, actualizando dependencias..."
    docker-compose exec -T app composer install --optimize-autoloader --no-interaction
fi

# 3. Ejecutar migraciones si hay nuevas
if git diff HEAD@{1} --name-only | grep -q "database/migrations"; then
    echo "🗄️  Detectadas nuevas migraciones, ejecutando..."
    docker-compose exec -T app php artisan migrate
fi

# 4. Limpiar caché
echo "🧹 Limpiando caché..."
docker-compose exec -T app php artisan optimize:clear

echo "✅ Actualización completada!"
