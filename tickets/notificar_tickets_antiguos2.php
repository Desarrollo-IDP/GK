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
 * - Agrupa tickets por empresa en un solo correo
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

    // Agrupar tickets por empresa
    $tickets_por_empresa = [];
    
    while ($row = $result->fetch_assoc()) {
        $id_empresa = $row['id_empresa'];
        if (!isset($tickets_por_empresa[$id_empresa])) {
            $tickets_por_empresa[$id_empresa] = [
                'empresa_nombre' => $row['empresa_nombre'],
                'tickets' => []
            ];
        }
        $tickets_por_empresa[$id_empresa]['tickets'][] = $row;
    }

    logMessage("Empresas con tickets sin asignar: " . count($tickets_por_empresa));
    
    // Enviar un correo por cada empresa
    foreach ($tickets_por_empresa as $id_empresa => $data_empresa) {
        $empresa_nombre = $data_empresa['empresa_nombre'] ?: "Empresa ID: $id_empresa";
        $tickets = $data_empresa['tickets'];
        $cantidad_tickets = count($tickets);
        $total_tickets_notificados += $cantidad_tickets;
        
        logMessage("Procesando empresa: {$empresa_nombre} (ID: {$id_empresa}) - {$cantidad_tickets} ticket(s)");
        
        try {
            // Obtener configuración de email de la empresa
            $email_config = getEmpresaEmailConfig($id_empresa, $conn);
            
            if (!$email_config || empty($email_config['smtp_from_email'])) {
                logMessage("ADVERTENCIA: No se encontró configuración de email para empresa ID: {$id_empresa}", true);
                $total_errores++;
                continue;
            }
            
            $correo_destino = $email_config['smtp_from_email'];
            
            // Construir lista de tickets en HTML
            $lista_tickets_html = '';
            foreach ($tickets as $ticket) {
                $ticket_number = htmlspecialchars($ticket['ticket_number']);
                $ticket_subject = htmlspecialchars($ticket['subject'] ?: 'Sin asunto');
                $ticket_description = htmlspecialchars($ticket['description'] ?: 'Sin descripción');
                $ticket_created = date('d/m/Y H:i', strtotime($ticket['created_at']));
                $ticket_url = $base_url . '/tickets/modules/tickets/view.php?id=' . $ticket['id'];
                $minutos_sin_asignar = round((time() - strtotime($ticket['created_at'])) / 60);
                
                $lista_tickets_html .= "
                    <div style='background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 15px; border-left: 4px solid #ffc107;'>
                        <div style='display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;'>
                            <h4 style='margin: 0; color: #2c3e50; font-size: 16px;'>
                                <a href='$ticket_url' style='color: #007bff; text-decoration: none;'>Ticket #$ticket_number</a>
                            </h4>
                            <span style='background: #ffc107; color: #000; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;'>
                                $minutos_sin_asignar min sin asignar
                            </span>
                        </div>
                        <p style='margin: 5px 0; color: #495057; font-weight: 600;'>$ticket_subject</p>
                        <p style='margin: 10px 0 5px 0; color: #6c757d; font-size: 14px; line-height: 1.5;'>
                            " . nl2br($ticket_description) . "
                        </p>
                        <p style='margin: 10px 0 0 0; color: #6c757d; font-size: 12px;'>
                            <i class='fas fa-calendar'></i> Creado: $ticket_created
                        </p>
                    </div>";
            }
            
            $asunto = "⚠️ $cantidad_tickets ticket(s) sin asignar - $empresa_nombre";
            
            $mensaje = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Notificación Tickets Sin Asignar</title>
            </head>
            <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                <div style='max-width: 700px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); padding: 30px; text-align: center; color: white;'>
                        <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>Sistema de Tickets</h2>
                        <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>⚠️ Tickets Sin Asignar</p>
                    </div>
                    <div style='padding: 40px 30px;'>
                        <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                            Hola,
                        </p>
                        <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                            Se han detectado <strong>$cantidad_tickets ticket(s)</strong> de la empresa <strong>$empresa_nombre</strong> que llevan más de <strong>30 minutos sin asignar</strong> (status: Sin Asignar).
                        </p>
                        
                        <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #ffc107;'>
                            <h4 style='margin: 0 0 10px 0; color: #856404; font-size: 16px;'>
                                <i style='margin-right: 8px;'>⏰</i> ¿Por qué es importante asignar estos tickets?
                            </h4>
                            <p style='margin: 0; color: #856404; font-size: 14px;'>
                                Los tickets sin asignar no están siendo atendidos por ningún técnico. Es importante asignarlos lo antes posible para garantizar un tiempo de respuesta adecuado y una buena experiencia del cliente.
                            </p>
                        </div>
                        
                        <h3 style='margin: 30px 0 20px 0; color: #2c3e50; font-size: 18px; border-bottom: 2px solid #dee2e6; padding-bottom: 10px;'>
                            Tickets Pendientes de Asignación:
                        </h3>
                        
                        $lista_tickets_html
                        
                        <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #28a745;'>
                            <h4 style='margin: 0 0 10px 0; color: #155724; font-size: 16px;'>
                                <i style='margin-right: 8px;'>✅</i> Acción Requerida
                            </h4>
                            <p style='margin: 0; color: #155724; font-size: 14px;'>
                                Por favor, revisa estos tickets y asígnalos a un técnico disponible lo antes posible para iniciar su atención.
                            </p>
                        </div>
                        
                        <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
                            Este es un mensaje automático del sistema de tickets.
                        </p>
                    </div>
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                        <div style='color: #6c757d; font-size: 12px;'>
                            <p style='margin: 0 0 10px 0;'>
                                <strong>GK</strong> 
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
                logMessage("ERROR: No se pudo crear instancia de PHPMailer para empresa ID: {$id_empresa}", true);
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
                    $total_empresas_notificadas++;
                    logMessage("✓ Notificación enviada exitosamente a: {$correo_destino} para empresa {$empresa_nombre} ({$cantidad_tickets} tickets)");
                } else {
                    $total_errores++;
                    logMessage("✗ Error al enviar notificación para empresa ID: {$id_empresa} - " . $mail->ErrorInfo, true);
                }
            } catch (Exception $e) {
                $total_errores++;
                logMessage("EXCEPCIÓN al enviar correo para empresa ID: {$id_empresa} - " . $e->getMessage(), true);
            }
            
        } catch (Exception $e) {
            $total_errores++;
            logMessage("EXCEPCIÓN procesando empresa {$id_empresa}: " . $e->getMessage(), true);
            // Continuar con la siguiente empresa
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
