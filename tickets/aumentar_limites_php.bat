@echo off
echo ========================================
echo   AUMENTAR LIMITES DE PHP EN XAMPP
echo ========================================
echo.
echo Este script te ayudara a aumentar los limites de PHP
echo para permitir la subida de archivos mas grandes.
echo.
echo Archivo a modificar: C:\xampp\php\php.ini
echo.
pause

echo.
echo Haciendo backup del php.ini actual...
copy "C:\xampp\php\php.ini" "C:\xampp\php\php.ini.backup_%date:~-4,4%%date:~-7,2%%date:~-10,2%_%time:~0,2%%time:~3,2%%time:~6,2%" >nul
if %errorlevel% equ 0 (
    echo [OK] Backup creado exitosamente
) else (
    echo [ERROR] No se pudo crear el backup
    pause
    exit /b 1
)

echo.
echo Abriendo php.ini en Notepad...
echo.
echo IMPORTANTE: Busca y modifica estas lineas:
echo.
echo   post_max_size = 200M
echo   upload_max_filesize = 100M
echo   max_file_uploads = 50
echo   max_execution_time = 300
echo   max_input_time = 300
echo.
echo Despues de guardar los cambios, debes REINICIAR APACHE
echo desde el Panel de Control de XAMPP.
echo.

notepad "C:\xampp\php\php.ini"

echo.
echo ========================================
echo   SIGUIENTE PASO: REINICIAR APACHE
echo ========================================
echo.
echo 1. Abre XAMPP Control Panel
echo 2. Haz clic en "Stop" en Apache
echo 3. Espera unos segundos
echo 4. Haz clic en "Start" en Apache
echo.
echo Presiona cualquier tecla cuando hayas reiniciado Apache...
pause >nul

echo.
echo Verificando nuevos limites...
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'max_file_uploads: ' . ini_get('max_file_uploads') . PHP_EOL;"
php -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;"

echo.
echo ========================================
echo   PROCESO COMPLETADO
echo ========================================
echo.
echo Si los valores mostrados arriba son los que configuraste,
echo el cambio fue exitoso.
echo.
echo Si NO cambiaron:
echo   1. Verifica que guardaste el archivo php.ini
echo   2. Asegurate de haber reiniciado Apache
echo   3. Verifica que editaste el archivo correcto
echo.
pause

