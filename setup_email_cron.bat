@echo off
REM =====================================================
REM Script de Configuración: Tarea Programada para Procesamiento de Emails
REM Windows Task Scheduler
REM =====================================================

echo ================================================
echo Configuracion de Tarea Programada
echo Procesador de Emails Multi-Empresa
echo ================================================
echo.

REM Obtener la ruta del script PHP
set PHP_PATH=C:\xampp\php\php.exe
set SCRIPT_PATH=%~dp0tickets\cron\email_processor.php

REM Verificar que PHP existe
if not exist "%PHP_PATH%" (
    echo ERROR: No se encontro PHP en: %PHP_PATH%
    echo Por favor, modifica PHP_PATH en este archivo con la ruta correcta.
    pause
    exit /b 1
)

REM Verificar que el script existe
if not exist "%SCRIPT_PATH%" (
    echo ERROR: No se encontro el script en: %SCRIPT_PATH%
    pause
    exit /b 1
)

echo Configurando tarea programada...
echo PHP: %PHP_PATH%
echo Script: %SCRIPT_PATH%
echo.

REM Verificar permisos de administrador
net session >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ================================================
    echo ERROR: Este script requiere permisos de Administrador
    echo ================================================
    echo.
    echo Por favor:
    echo 1. Cierra este script
    echo 2. Haz clic derecho en este archivo
    echo 3. Selecciona "Ejecutar como administrador"
    echo.
    pause
    exit /b 1
)

REM Crear la tarea programada
echo Creando tarea programada...
schtasks /create /tn "GK_EmailProcessor" ^
    /tr "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" ^
    /sc minute ^
    /mo 5 ^
    /ru SYSTEM ^
    /rl HIGHEST ^
    /f

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ================================================
    echo Tarea programada configurada correctamente!
    echo ================================================
    echo.
    echo La tarea se ejecutara cada 5 minutos.
    echo.
    echo Para verificar la tarea:
    echo   schtasks /query /tn "GK_EmailProcessor"
    echo.
    echo Para eliminar la tarea:
    echo   schtasks /delete /tn "GK_EmailProcessor" /f
    echo.
) else (
    echo.
    echo ERROR: No se pudo crear la tarea programada.
    echo Asegurate de ejecutar este script como Administrador.
    echo.
)

pause

