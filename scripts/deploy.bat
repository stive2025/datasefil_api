@echo off
REM Script de despliegue para Windows

echo Iniciando despliegue...
echo.

REM 1. Obtener ultimos cambios de Git
echo Descargando cambios desde Git...
git pull origin main
if errorlevel 1 goto error

REM 2. Reconstruir contenedores
echo Reconstruyendo contenedores...
docker-compose build --no-cache app
if errorlevel 1 goto error

REM 3. Detener contenedores
echo Deteniendo contenedores...
docker-compose down
if errorlevel 1 goto error

REM 4. Iniciar contenedores
echo Iniciando contenedores...
docker-compose up -d
if errorlevel 1 goto error

REM 5. Esperar a que los contenedores esten listos
echo Esperando a que los contenedores esten listos...
timeout /t 5 /nobreak > nul

REM 6. Instalar/actualizar dependencias
echo Instalando dependencias...
docker-compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 goto error

REM 7. Ejecutar migraciones
echo Ejecutando migraciones...
docker-compose exec -T app php artisan migrate --force
if errorlevel 1 goto error

REM 8. Limpiar y optimizar cache
echo Limpiando cache...
docker-compose exec -T app php artisan optimize:clear
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

REM 9. Reiniciar servicios
echo Reiniciando servicios...
docker-compose restart app

echo.
echo Despliegue completado exitosamente!
echo.
echo Estado de los contenedores:
docker-compose ps
goto end

:error
echo.
echo ERROR: El despliegue fallo!
exit /b 1

:end
