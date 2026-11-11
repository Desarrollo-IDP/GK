#!/bin/bash
# =====================================================
# Script de Configuración: Cron Job para Procesamiento de Emails
# Linux Crontab
# =====================================================

echo "================================================"
echo "Configuración de Cron Job"
echo "Procesador de Emails Multi-Empresa"
echo "================================================"
echo ""

# Obtener la ruta del script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SCRIPT_PATH="$SCRIPT_DIR/tickets/cron/email_processor.php"
LOG_PATH="/var/log/email_processor.log"

# Verificar que el script existe
if [ ! -f "$SCRIPT_PATH" ]; then
    echo "ERROR: No se encontró el script en: $SCRIPT_PATH"
    exit 1
fi

# Obtener la ruta de PHP
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    PHP_PATH="/usr/bin/php"
    echo "ADVERTENCIA: No se encontró PHP en PATH, usando: $PHP_PATH"
fi

# Verificar que PHP existe
if [ ! -f "$PHP_PATH" ]; then
    echo "ERROR: No se encontró PHP en: $PHP_PATH"
    echo "Por favor, instala PHP o modifica PHP_PATH en este script."
    exit 1
fi

echo "PHP: $PHP_PATH"
echo "Script: $SCRIPT_PATH"
echo "Log: $LOG_PATH"
echo ""

# Crear el comando cron
CRON_COMMAND="*/5 * * * * $PHP_PATH $SCRIPT_PATH >> $LOG_PATH 2>&1"

# Verificar si ya existe una entrada similar
EXISTING_CRON=$(crontab -l 2>/dev/null | grep -F "$SCRIPT_PATH")

if [ ! -z "$EXISTING_CRON" ]; then
    echo "ADVERTENCIA: Ya existe una entrada cron para este script."
    echo "Entrada existente:"
    echo "  $EXISTING_CRON"
    echo ""
    read -p "¿Deseas reemplazarla? (s/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        echo "Operación cancelada."
        exit 0
    fi
    # Eliminar entrada existente
    crontab -l 2>/dev/null | grep -v "$SCRIPT_PATH" | crontab -
fi

# Agregar nueva entrada al crontab
(crontab -l 2>/dev/null; echo "$CRON_COMMAND") | crontab -

if [ $? -eq 0 ]; then
    echo "================================================"
    echo "Cron job configurado correctamente!"
    echo "================================================"
    echo ""
    echo "La tarea se ejecutará cada 5 minutos."
    echo ""
    echo "Para verificar el cron job:"
    echo "  crontab -l"
    echo ""
    echo "Para ver los logs:"
    echo "  tail -f $LOG_PATH"
    echo ""
    echo "Para eliminar el cron job:"
    echo "  crontab -l | grep -v '$SCRIPT_PATH' | crontab -"
    echo ""
else
    echo "ERROR: No se pudo configurar el cron job."
    exit 1
fi

