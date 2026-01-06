@echo off
REM Script rapido para actualizar en desarrollo (Windows)

echo Actualizando aplicacion en modo desarrollo...
echo.

REM 1. Obtener ultimos cambios de Git
echo Descargando cambios desde Git...
git pull origin main
if errorlevel 1 goto error

REM 2. Actualizar dependencias
echo Actualizando dependencias...
docker-compose exec -T app composer install --optimize-autoloader --no-interaction
if errorlevel 1 goto error

REM 3. Ejecutar migraciones
echo Ejecutando migraciones...
docker-compose exec -T app php artisan migrate
if errorlevel 1 goto error

REM 4. Limpiar cache
echo Limpiando cache...
docker-compose exec -T app php artisan optimize:clear
if errorlevel 1 goto error

echo.
echo Actualizacion completada!
goto end

:error
echo.
echo ERROR: La actualizacion fallo!
exit /b 1

:end
