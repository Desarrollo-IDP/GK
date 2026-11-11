<?php
/**
 * Funciones de Procesamiento de Emails
 * Funciones específicas para el procesamiento automático de emails por empresa
 */

require_once(__DIR__ . '/config_email_processor.php');
require_once(__DIR__ . '/../includes/email_processing_helper.php');
require_once(__DIR__ . '/../conexion/get_empresa_email_config.php');

// Incluir funciones necesarias de outlook_emails.php
// Definir una constante para evitar ejecución del código principal
if (!defined('EMAIL_PROCESSOR_MODE')) {
    define('EMAIL_PROCESSOR_MODE', true);
}
require_once(__DIR__ . '/../modules/outlook_emails.php');

/**
 * Procesa los emails de una empresa específica
 * 
 * @param int $id_empresa ID de la empresa
 * @param mysqli $conn Conexión a la base de datos
 * @param int $max_emails Número máximo de correos a procesar
 * @return array Resultado del procesamiento con estadísticas
 */
function processEmpresaEmails(int $id_empresa, mysqli $conn, int $max_emails = 50): array {
    $start_time = microtime(true);
    $result = [
        'success' => false,
        'emails_found' => 0,
        'emails_processed' => 0,
        'error_message' => null,
        'execution_time' => 0.00
    ];
    
    $inbox = null;
    
    try {
        ensureDbConnection();
        
        // Obtener configuración IMAP para esta empresa
        $imap_config = getImapConfigForEmpresa($id_empresa, $conn);
        
        if (!$imap_config) {
            throw new Exception("No se pudo obtener configuración IMAP para empresa {$id_empresa}");
        }
        
        // Establecer empresa_id en contexto global para usar en processEmail
        $GLOBALS['current_empresa_id'] = $id_empresa;
        
        // Conectar a IMAP con timeout
        $timeout = defined('EMAIL_PROCESSOR_IMAP_CONNECTION_TIMEOUT') ? 
                   EMAIL_PROCESSOR_IMAP_CONNECTION_TIMEOUT : 30;
        
        // Construir cadena de conexión IMAP
        // Si imap_options ya contiene el prefijo de encryption, usar solo options
        // De lo contrario, concatenar encryption + options
        $encryption = $imap_config['encryption'] ?? '/imap/ssl';
        $options = $imap_config['options'] ?? '';
        
        // Si options ya contiene el prefijo de encryption, usar solo options
        if (!empty($options) && strpos($options, $encryption) === 0) {
            $imap_path = $options;
        } else {
            // Concatenar encryption + options (sin duplicar el prefijo)
            $imap_path = $encryption . $options;
        }
        
        $mailbox_string = "{" . $imap_config['host'] . ":" . $imap_config['port'] . 
                         $imap_path . "}" . 
                         $imap_config['folder'];
        
        // Debug: Log de la cadena construida
        emailProcessLog("DEBUG IMAP conexión para empresa {$id_empresa}: {$mailbox_string}");
        
        // Limpiar errores previos de IMAP
        @imap_errors();
        @imap_alerts();
        
        // Intentar conectar con timeout (sin OP_HALFOPEN para poder buscar correos)
        $inbox = @imap_open($mailbox_string, $imap_config['username'], $imap_config['password'], 
                           0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
        
        if (!$inbox) {
            $error = @imap_last_error();
            // Limpiar errores de IMAP para evitar notices en shutdown
            @imap_errors();
            @imap_alerts();
            throw new Exception("Error al conectar con IMAP: " . ($error ?: 'Error desconocido'));
        }
        
        // Establecer inbox en contexto global para que processEmail pueda accederlo
        $GLOBALS['inbox'] = $inbox;
        
        // Limitar la búsqueda a mensajes recibidos desde hoy, incluyendo leídos y no leídos.
        $since_date = emailImapSinceTodayDate();
        $search_criteria = 'SINCE "' . $since_date . '"';
        emailProcessLog("Buscando correos recibidos desde {$since_date} para empresa {$id_empresa}...");
        $all_uids = @imap_search($inbox, $search_criteria, FT_UID);
        
        // Contar correos no leídos para logging
        $unseen_uids = @imap_search($inbox, 'UNSEEN ' . $search_criteria, FT_UID);
        $unseen_count = $unseen_uids ? count($unseen_uids) : 0;
        emailProcessLog("DEBUG: Correos no leídos encontrados: " . ($unseen_count > 0 ? $unseen_count : 'ninguno'));
        emailProcessLog("DEBUG: Correos totales encontrados: " . ($all_uids ? count($all_uids) : 0));
        
        // Filtrar correos que ya fueron procesados usando verificarCorreoProcesado
        // que verifica tanto el mensaje como el ticket válido asociado
        $email_uids = [];
        
        if ($all_uids && count($all_uids) > 0) {
            foreach ($all_uids as $uid) {
                // Obtener header para extraer message_id y date
                $header_raw = @imap_fetchheader($inbox, $uid, FT_UID);
                $message_id = '';
                $date = '';
                $from_email = '';
                
                if ($header_raw) {
                    if (preg_match('/Message-ID: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
                        $message_id = trim($matches[1], '<>');
                        // Normalizar message_id: convertir a minúsculas para comparación consistente
                        if (!empty($message_id)) {
                            $message_id = strtolower(trim($message_id));
                        }
                    }
                    if (preg_match('/Date: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
                        $date = date('Y-m-d H:i:s', strtotime(trim($matches[1])));
                    }
                    // Extraer remitente para generar Message-ID si falta
                    $fromParsed = parseEmailAddressFromHeader($header_raw);
                    $from_email = $fromParsed['email'];
                }
                
                // Si Message-ID está vacío, generar uno único usando la función auxiliar
                if (empty($message_id)) {
                    if (function_exists('generarMessageIdUnico')) {
                        $message_id = generarMessageIdUnico($uid, $date, $from_email, $id_empresa);
                    } else {
                        // Fallback si la función no está disponible
                        $timestamp = !empty($date) ? strtotime($date) : time();
                        $date_str = date('YmdHis', $timestamp);
                        $from_str = !empty($from_email) ? $from_email : 'unknown';
                        $message_id = 'generated-' . md5($uid . '-' . $date_str . '-' . $from_str . '-' . $id_empresa) . '@' . 'empresa' . $id_empresa . '.local';
                    }
                }
                
                // Usar verificarCorreoProcesado que verifica ticket válido
                // Pasar empresa_id para verificar solo correos procesados para esta empresa
                if (function_exists('verificarCorreoProcesado')) {
                    if (!verificarCorreoProcesado($uid, $message_id, $date, $id_empresa)) {
                        // El correo no ha sido procesado o no tiene ticket válido, agregarlo a la lista
                        $email_uids[] = $uid;
                    } else {
                        emailProcessLog("Correo UID {$uid} (Message-ID: {$message_id}) ya fue procesado anteriormente con ticket válido para empresa {$id_empresa}, omitiendo...");
                    }
                } else {
                    // Fallback: si la función no existe, agregar el correo para procesarlo
                    emailProcessLog("ADVERTENCIA: verificarCorreoProcesado no disponible, procesando correo UID {$uid}");
                    $email_uids[] = $uid;
                }
            }
            
            emailProcessLog("De " . count($all_uids) . " correos totales en la bandeja de entrada, " . count($email_uids) . " no han sido procesados o no tienen ticket válido para empresa {$id_empresa}");
        } else {
            emailProcessLog("No se encontraron correos en la bandeja de entrada para empresa {$id_empresa}");
        }
        
        if (!$email_uids || count($email_uids) === 0) {
            // No hay correos para procesar (todos ya fueron procesados o no hay correos en la bandeja)
            if ($all_uids && count($all_uids) > 0) {
                emailProcessLog("Todos los correos en la bandeja de entrada ya fueron procesados para empresa {$id_empresa} (Total: " . count($all_uids) . ", No leídos: {$unseen_count})");
            } else {
                emailProcessLog("No se encontraron correos en la bandeja de entrada para empresa {$id_empresa}");
            }
            $result['success'] = true;
            $result['emails_found'] = 0;
            $result['emails_processed'] = 0;
        } else {
            // Limitar número de correos a procesar
            if (count($email_uids) > $max_emails) {
                $email_uids = array_slice($email_uids, 0, $max_emails);
            }
            
            $result['emails_found'] = count($email_uids);
            emailProcessLog("Encontrados " . count($email_uids) . " correos para procesar para empresa {$id_empresa}");
            $processed_count = 0;
            
            foreach ($email_uids as $email_uid) {
                try {
                    emailProcessLog("Procesando email UID {$email_uid} para empresa {$id_empresa}");
                    
                    // Verificar primero si el correo ya fue procesado antes de llamar a processEmail
                    // Esto evita incrementar el contador cuando el correo ya fue procesado
                    $header_raw = @imap_fetchheader($inbox, $email_uid, FT_UID);
                    $message_id = '';
                    $date = '';
                    $from_email = '';
                    
                    if ($header_raw) {
                        if (preg_match('/Message-ID: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
                            $message_id = trim($matches[1], '<>');
                            // Normalizar message_id: convertir a minúsculas para comparación consistente
                            if (!empty($message_id)) {
                                $message_id = strtolower(trim($message_id));
                            }
                        }
                        if (preg_match('/Date: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
                            $date = date('Y-m-d H:i:s', strtotime(trim($matches[1])));
                        }
                        // Extraer remitente para generar Message-ID si falta Message-ID
                        $fromParsed = parseEmailAddressFromHeader($header_raw);
                        $from_email = $fromParsed['email'];
                    }
                    
                    // Si Message-ID está vacío, generar uno único usando la función auxiliar
                    if (empty($message_id)) {
                        if (function_exists('generarMessageIdUnico')) {
                            $message_id = generarMessageIdUnico($email_uid, $date, $from_email, $id_empresa);
                        } else {
                            // Fallback si la función no está disponible
                            $timestamp = !empty($date) ? strtotime($date) : time();
                            $date_str = date('YmdHis', $timestamp);
                            $from_str = !empty($from_email) ? $from_email : 'unknown';
                            $message_id = 'generated-' . md5($email_uid . '-' . $date_str . '-' . $from_str . '-' . $id_empresa) . '@' . 'empresa' . $id_empresa . '.local';
                        }
                    }
                    
                    // Verificar si ya fue procesado (la función está disponible porque outlook_emails.php ya está incluido)
                    // Pasar empresa_id para verificar solo correos procesados para esta empresa
                    if (function_exists('verificarCorreoProcesado')) {
                        if (verificarCorreoProcesado($email_uid, $message_id, $date, $id_empresa)) {
                            emailProcessLog("Correo UID {$email_uid} (Message-ID: {$message_id}) ya procesado anteriormente para empresa {$id_empresa}, omitiendo del contador");
                            continue; // No incrementar el contador
                        }
                    }
                    
                    // Procesar cada correo
                    $email_processed = processEmail($email_uid);
                    if ($email_processed === true) {
                        $processed_count++;
                        emailProcessLog("Email UID {$email_uid} procesado exitosamente");
                    } else {
                        emailProcessLog("ADVERTENCIA: processEmail retornó false para UID {$email_uid}");
                    }
                } catch (Exception $e) {
                    emailProcessLog("Error procesando email UID {$email_uid} para empresa {$id_empresa}: " . $e->getMessage(), 'error');
                    emailProcessLog("Stack trace: " . $e->getTraceAsString(), 'debug');
                    // Continuar con el siguiente correo
                    continue;
                }
            }
            
            $result['emails_processed'] = $processed_count;
            $result['success'] = true;
            emailProcessLog("Procesamiento completado para empresa {$id_empresa}: {$processed_count} de {$result['emails_found']} correos procesados");
        }
        
    } catch (Exception $e) {
        $result['success'] = false;
        $result['error_message'] = $e->getMessage();
        emailProcessLog("Error procesando emails para empresa {$id_empresa}: " . $e->getMessage());
    } finally {
        // Cerrar conexión IMAP si está abierta y es un recurso válido
        if ($inbox !== null && $inbox !== false) {
            if (is_resource($inbox) || (is_object($inbox) && get_class($inbox) === 'IMAP\Connection')) {
                @imap_close($inbox);
            }
        }
        
        // Limpiar cualquier error residual de IMAP para evitar notices en shutdown
        @imap_errors();
        @imap_alerts();
        
        // Calcular tiempo de ejecución
        $result['execution_time'] = round(microtime(true) - $start_time, 2);
    }
    
    return $result;
}

/**
 * Procesa emails para una empresa con timeout y manejo de errores robusto
 * 
 * @param array $empresa_config Configuración de la empresa
 * @param mysqli $conn Conexión a la base de datos
 * @return bool True si el procesamiento fue exitoso (aunque haya errores parciales)
 */
function processEmpresaWithTimeout(array $empresa_config, mysqli $conn): bool {
    $id_empresa = $empresa_config['id_empresa'];
    $max_emails = $empresa_config['max_emails_per_run'] ?? EMAIL_PROCESSOR_MAX_EMAILS_PER_EMPRESA;
    $timeout = EMAIL_PROCESSOR_TIMEOUT_PER_EMPRESA;
    
    $start_time = microtime(true);
    $status = 'error';
    $error_message = null;
    
    try {
        // Configurar timeout para la ejecución
        set_time_limit($timeout + 10); // Dar un poco más de margen
        
        // Procesar emails
        $result = processEmpresaEmails($id_empresa, $conn, $max_emails);
        
        $execution_time = round(microtime(true) - $start_time, 2);
        
        // Verificar timeout
        if ($execution_time >= $timeout) {
            $status = 'timeout';
            $error_message = "Timeout alcanzado después de {$execution_time} segundos";
            logEmailProcessing($conn, $id_empresa, 'timeout', 
                             $result['emails_processed'], 
                             $result['emails_found'], 
                             $execution_time, 
                             $error_message);
            updateEmpresaProcessingStatus($conn, $id_empresa, false, $error_message);
            return false;
        }
        
        // Registrar resultado
        if ($result['success']) {
            $status = 'success';
            logEmailProcessing($conn, $id_empresa, 'success', 
                             $result['emails_processed'], 
                             $result['emails_found'], 
                             $execution_time);
            updateEmpresaProcessingStatus($conn, $id_empresa, true);
            return true;
        } else {
            $status = 'error';
            $error_message = $result['error_message'];
            logEmailProcessing($conn, $id_empresa, 'error', 
                             $result['emails_processed'], 
                             $result['emails_found'], 
                             $execution_time, 
                             $error_message);
            updateEmpresaProcessingStatus($conn, $id_empresa, false, $error_message);
            return false;
        }
        
    } catch (Exception $e) {
        $execution_time = round(microtime(true) - $start_time, 2);
        $error_message = $e->getMessage();
        
        logEmailProcessing($conn, $id_empresa, 'error', 0, 0, $execution_time, $error_message);
        updateEmpresaProcessingStatus($conn, $id_empresa, false, $error_message);
        
        return false;
    }
}
