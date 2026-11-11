<?php
/**
 * Procesador Automático de Emails Multi-Empresa
 * Script principal para ejecución automática mediante cron
 * 
 * Este script procesa emails para múltiples empresas de forma controlada:
 * - Procesa máximo 3 empresas por ejecución
 * - Timeout de 4 minutos por empresa
 * - Límite de 50 correos por empresa por ejecución
 * - Manejo robusto de errores
 * - Logging estructurado
 * 
 * Ejecutar cada 5 minutos mediante cron:
 * Windows: schtasks /create /tn "GK_EmailProcessor" /tr "php email_processor.php" /sc minute /mo 5
 * Linux: Ejecutar cada 5 minutos con crontab (ver setup_email_cron.sh)
 */

// Evitar ejecución directa desde navegador (opcional, comentar para pruebas)
if (php_sapi_name() !== 'cli' && !isset($_GET['allow_browser'])) {
    die('Este script solo puede ejecutarse desde línea de comandos o con ?allow_browser=1');
}

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Función helper para mostrar mensajes en consola y logs (solo esencial)
function logMessage($message, $isError = false) {
    $timestamp = date('Y-m-d H:i:s');
    $level = $isError ? 'error' : 'info';

    // Consola CLI: errores siempre; info solo si DEBUG
    if (php_sapi_name() === 'cli') {
        $show = $isError || (defined('EMAIL_PROCESSOR_DEBUG') && EMAIL_PROCESSOR_DEBUG);
        if ($show) {
            $formattedMessage = "[{$timestamp}] {$message}" . PHP_EOL;
            if ($isError) {
                fwrite(STDERR, $formattedMessage);
            } else {
                echo $formattedMessage;
            }
        }
    }

    if (function_exists('emailProcessLog')) {
        emailProcessLog($message, $level);
    } elseif ($isError) {
        error_log($message);
    }
}

// Cargar dependencias
require_once(__DIR__ . '/../conexion/db.php');
require_once(__DIR__ . '/config_email_processor.php');
require_once(__DIR__ . '/email_processor_functions.php');
require_once(__DIR__ . '/../includes/email_processing_helper.php');

// Verificar conexión a base de datos
if (!$conn) {
    logMessage("ERROR: No se pudo establecer la conexión con la base de datos", true);
    exit(1);
}

// Verificar extensión IMAP
if (!extension_loaded('imap')) {
    logMessage("ERROR: La extensión IMAP no está instalada en el servidor.", true);
    exit(1);
}

// Registrar función de limpieza para evitar notices de IMAP en shutdown
register_shutdown_function(function () {
    if (function_exists('imap_errors')) {
        @imap_errors();
    }
    if (function_exists('imap_alerts')) {
        @imap_alerts();
    }
});

$script_start_time = microtime(true);
$total_empresas_processed = 0;

try {
    // Obtener empresas pendientes de procesamiento
    $max_empresas = EMAIL_PROCESSOR_MAX_EMPRESAS_PER_RUN;
    $empresas = getEmpresasPendingProcessing($conn, $max_empresas);
    
    if (empty($empresas)) {
        exit(0);
    }
    
    // Procesar cada empresa
    foreach ($empresas as $empresa_config) {
        $id_empresa = $empresa_config['id_empresa'];
        $empresa_nombre = $empresa_config['empresa_nombre'] ?? "Empresa ID {$id_empresa}";
        
        try {
            $success = processEmpresaWithTimeout($empresa_config, $conn);
            
            if ($success) {
                $total_empresas_processed++;
                if (function_exists('emailProcessLog')) {
                    emailProcessLog(
                        "Empresa {$empresa_nombre}: OK",
                        'debug'
                    );
                }
            } else {
                logMessage("Error procesando empresa {$empresa_nombre} (ID: {$id_empresa})", true);
            }
            
        } catch (Exception $e) {
            logMessage("Excepción en empresa {$empresa_nombre} (ID: {$id_empresa}): " . $e->getMessage(), true);
            continue;
        }
    }
    
} catch (Exception $e) {
    logMessage("ERROR CRÍTICO en procesador de emails: " . $e->getMessage(), true);
    exit(1);
}

$script_execution_time = round(microtime(true) - $script_start_time, 2);

if ($total_empresas_processed > 0 && function_exists('emailProcessLog')) {
    emailProcessLog(
        "Procesamiento terminado: {$total_empresas_processed} empresa(s), {$script_execution_time}s",
        emailProcessorDebugEnabled() ? 'info' : 'debug'
    );
}

// Limpiar errores IMAP antes de salir
if (function_exists('imap_errors')) {
    @imap_errors();
}
if (function_exists('imap_alerts')) {
    @imap_alerts();
}

exit(0);

