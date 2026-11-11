<?php
/**
 * Notificador de Tickets Sin Asignar
 * Script para ejecución automática mediante tarea programada
 * 
 * Este script busca tickets sin asignar (status_id = 1) que tengan más de 30 minutos
 * desde su creación y envía notificaciones agrupadas por empresa.
 * 
 * Características:
 * - Filtra por empresa (id_empresa)
 * - Solo tickets con status_id = 1 (Sin Asignar)
 * - Excluye tickets eliminados (deleted = 0)
 * - Tiempo mínimo: 30 minutos desde creación
 * - Envía correo al email configurado en empresa_email_config
 * - Envía un correo individual por cada ticket sin asignar
 * 
 * Ejecutar cada 15-30 minutos mediante tarea programada:
 * Windows: schtasks /create /tn "GK_NotificarTicketsSinAsignar" /tr "php C:\xampp\htdocs\htdocs-securiti\tickets\notificar_tickets_antiguos.php" /sc minute /mo 30
 
 */

// Evitar ejecución directa desde navegador (opcional, comentar para pruebas)
if (php_sapi_name() !== 'cli' && !isset($_GET['allow_browser'])) {
    die('Este script solo puede ejecutarse desde línea de comandos o con ?allow_browser=1');
}

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Función helper para mostrar mensajes en consola y logs
function logMessage($message, $isError = false) {
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    
    // Mostrar en consola cuando se ejecuta desde CLI
    if (php_sapi_name() === 'cli') {
        if ($isError) {
            fwrite(STDERR, $formattedMessage);
        } else {
            echo $formattedMessage;
        }
    }
    
    // Registrar en log de PHP
    error_log($message);
}

// Cargar dependencias
require_once __DIR__ . '/conexion/db.php'; 
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/conexion/config_mailer.php';
require_once __DIR__ . '/conexion/get_empresa_email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verificar conexión a base de datos
if (!$conn) {
    logMessage("ERROR: No se pudo establecer la conexión con la base de datos", true);
    exit(1);
}

logMessage("=== Notificador de Tickets Sin Asignar ===");
logMessage("Iniciando verificación del sistema...");

$base_url = 'http://localhost:90/GK';

// Iniciar procesamiento
$script_start_time = microtime(true);
$total_empresas_notificadas = 0;
$total_tickets_notificados = 0;
$total_correos_enviados = 0;
$total_errores = 0;
$empresas_notificadas = []; // Para contar empresas únicas

logMessage("=== Iniciando búsqueda de tickets sin asignar ===");
logMessage("Hora: " . date('Y-m-d H:i:s'));

try {
    // Obtener tickets sin asignar (status_id = 1) que tengan más de 30 minutos desde su creación
    // Agrupados por empresa para enviar un correo por empresa
    // Excluye tickets eliminados (deleted = 0 significa activo, deleted = 1 significa eliminado)
    $sql = "SELECT 
                t.id,
                t.ticket_number,
                t.subject,
                t.description,
                t.created_at,
                t.id_empresa,
                eu.nombre as empresa_nombre
            FROM tickets t
            LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
            WHERE t.status_id = 1 
                AND t.deleted = 0 
                AND t.id_empresa IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, t.created_at, NOW()) >= 30
            ORDER BY t.id_empresa, t.created_at ASC";

    $result = $conn->query($sql);

    if (!$result) {
        logMessage("ERROR: Error al ejecutar consulta SQL: " . $conn->error, true);
        exit(1);
    }

    if ($result->num_rows == 0) {
        logMessage("No se encontraron tickets sin asignar con más de 30 minutos de antigüedad.");
        logMessage("Procesamiento finalizado sin tickets para notificar.");
        exit(0);
    }

    logMessage("Tickets sin asignar encontrados: " . $result->num_rows);
    
    // Enviar un correo por cada ticket
    while ($ticket = $result->fetch_assoc()) {
        $ticket_id = $ticket['id'];
        $ticket_number = $ticket['ticket_number'];
        $ticket_subject = $ticket['subject'] ?: 'Sin asunto';
        $ticket_description = $ticket['description'] ?: 'Sin descripción';
        $ticket_created = $ticket['created_at'];
        $id_empresa = $ticket['id_empresa'];
        $empresa_nombre = $ticket['empresa_nombre'] ?: "Empresa ID: $id_empresa";
        
        $total_tickets_notificados++;
        
        logMessage("Procesando ticket #{$ticket_number} (ID: {$ticket_id}) - Empresa: {$empresa_nombre}");
        
        try {
            // Obtener configuración de email de la empresa
            $email_config = getEmpresaEmailConfig($id_empresa, $conn);
            
            if (!$email_config || empty($email_config['smtp_from_email'])) {
                logMessage("ADVERTENCIA: No se encontró configuración de email para empresa ID: {$id_empresa} - Ticket #{$ticket_number}", true);
                $total_errores++;
                continue;
            }
            
            $correo_destino = $email_config['smtp_from_email'];
            
            // Preparar datos del ticket para el correo
            $ticket_number_html = htmlspecialchars($ticket_number);
            $ticket_subject_html = htmlspecialchars($ticket_subject);
            $ticket_description_html = htmlspecialchars($ticket_description);
            $ticket_created_formatted = date('d/m/Y H:i', strtotime($ticket_created));
            $ticket_url = $base_url . '/tickets/modules/tickets/view.php?id=' . $ticket_id;
            $minutos_sin_asignar = round((time() - strtotime($ticket_created)) / 60);
            $horas_sin_asignar = floor($minutos_sin_asignar / 60);
            $minutos_restantes = $minutos_sin_asignar % 60;
            $tiempo_sin_asignar = $horas_sin_asignar > 0 ? "{$horas_sin_asignar}h {$minutos_restantes}min" : "{$minutos_sin_asignar}min";
            
            $asunto = "⚠️ Ticket #$ticket_number sin asignar - $empresa_nombre";
            
            $mensaje = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Ticket #$ticket_number_html Sin Asignar</title>
            </head>
            <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); padding: 30px; text-align: center; color: white;'>
                        <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>Sistema de Tickets</h2>
                        <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Ticket Sin Asignar</p>
                    </div>
                    <div style='padding: 40px 30px;'>
                        <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                            Hola,
                        </p>
                        <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                            Se ha detectado el <strong>Ticket #$ticket_number_html</strong> de la empresa <strong>$empresa_nombre</strong> que lleva más de <strong>30 minutos sin asignar</strong>.
                        </p>
                        
                        <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 25px; border-radius: 12px; border-left: 5px solid #ffc107; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                            <div style='display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;'>
                                <h3 style='margin: 0; color: #2c3e50; font-size: 20px;'>
                                    <a href='$ticket_url' style='color: #007bff; text-decoration: none;'>Ticket #$ticket_number_html</a>
                                </h3>
                                <span style='background: #ffc107; color: #000; padding: 6px 14px; border-radius: 16px; font-size: 13px; font-weight: 600; white-space: nowrap;'>
                                    $tiempo_sin_asignar sin asignar
                                </span>
                            </div>
                            
                            <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 15px;'>
                                <p style='margin: 0 0 10px 0; color: #495057; font-weight: 600; font-size: 16px;'>$ticket_subject_html</p>
                                <p style='margin: 0; color: #6c757d; font-size: 14px; line-height: 1.6;'>
                                    " . nl2br($ticket_description_html) . "
                                </p>
                            </div>
                            
                            <div style='display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;'>
                                <p style='margin: 0; color: #6c757d; font-size: 13px;'>
                                    <i style='margin-right: 5px;'>📅</i> Creado: $ticket_created_formatted
                                </p>
                            </div>
                        </div>
                        
                        <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #ffc107;'>
                            <h4 style='margin: 0 0 10px 0; color: #856404; font-size: 16px;'>
                                <i style='margin-right: 8px;'></i> ¿Por qué es importante asignar este ticket?
                            </h4>
                            <p style='margin: 0; color: #856404; font-size: 14px;'>
                                Este ticket no está siendo atendido por ningún técnico. Es importante asignarlo lo antes posible para garantizar un tiempo de respuesta adecuado y una buena experiencia del cliente.
                            </p>
                        </div>
                        
                        <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #28a745;'>
                            <h4 style='margin: 0 0 10px 0; color: #155724; font-size: 16px;'>
                                <i style='margin-right: 8px;'></i> Acción Requerida
                            </h4>
                            <p style='margin: 0; color: #155724; font-size: 14px;'>
                                Por favor, revisa este ticket y asígnalo a un técnico disponible lo antes posible para iniciar su atención.
                            </p>
                        </div>
                        
                        <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
                            Este es un mensaje automático del sistema de tickets.
                        </p>
                    </div>
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                        <div style='color: #6c757d; font-size: 12px;'>
                            <p style='margin: 0 0 10px 0;'>
                                <strong>SecuriTI</strong> 
                            </p>
                            <p style='margin: 0; opacity: 0.8;'>
                                Sistema de Gestión de Tickets
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";

            // Crear instancia de PHPMailer usando la configuración de la empresa
            $mail = createMailerForEmpresa($id_empresa, $conn);
            
            if (!$mail) {
                logMessage("ERROR: No se pudo crear instancia de PHPMailer para empresa ID: {$id_empresa} - Ticket #{$ticket_number}", true);
                $total_errores++;
                continue;
            }
            
            try {
                $mail->addAddress($correo_destino);
                $mail->Subject = $asunto;
                $mail->isHTML(true);
                $mail->Body = $mensaje;
                $mail->AltBody = strip_tags($mensaje);
                
                if ($mail->send()) {
                    $total_correos_enviados++;
                    // Contar empresas únicas notificadas
                    if (!isset($empresas_notificadas[$id_empresa])) {
                        $empresas_notificadas[$id_empresa] = true;
                        $total_empresas_notificadas++;
                    }
                    logMessage("✓ Notificación enviada exitosamente para Ticket #{$ticket_number} a: {$correo_destino} (Empresa: {$empresa_nombre})");
                } else {
                    $total_errores++;
                    logMessage("✗ Error al enviar notificación para Ticket #{$ticket_number} - " . $mail->ErrorInfo, true);
                }
            } catch (Exception $e) {
                $total_errores++;
                logMessage("EXCEPCIÓN al enviar correo para Ticket #{$ticket_number} - " . $e->getMessage(), true);
            }
            
        } catch (Exception $e) {
            $total_errores++;
            logMessage("EXCEPCIÓN procesando Ticket #{$ticket_number}: " . $e->getMessage(), true);
            // Continuar con el siguiente ticket
            continue;
        }
    }
    
} catch (Exception $e) {
    logMessage("ERROR CRÍTICO en notificador de tickets: " . $e->getMessage(), true);
    exit(1);
}

$script_execution_time = round(microtime(true) - $script_start_time, 2);

logMessage("=== Procesamiento completado ===");
logMessage("Empresas notificadas: {$total_empresas_notificadas}");
logMessage("Tickets notificados: {$total_tickets_notificados}");
logMessage("Correos enviados: {$total_correos_enviados}");
logMessage("Errores encontrados: {$total_errores}");
logMessage("Tiempo total de ejecución: {$script_execution_time} segundos");

// Salir con código de éxito o error según corresponda
if ($total_errores > 0 && $total_correos_enviados == 0) {
    exit(1); // Error si hubo errores y no se envió ningún correo
} else {
    exit(0); // Éxito
}
