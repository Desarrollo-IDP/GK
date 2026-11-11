<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
include '../../includes/ticket_history.php';
include '../../includes/ticket_status_tracking.php';
require_once '../../includes/ticket_display_helper.php';
require_once '../../includes/whatsapp_ticket_bridge.php';
require_once '../../../includes/empresa_helper.php';

// Incluir PHPMailer para envío de correos
require_once '../../vendor/autoload.php';
require_once '../../conexion/config_mailer.php';
require_once '../../conexion/get_empresa_email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$message = '';
$messageType = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: tickets.php');
    exit;
}

$id = $_GET['id'];

// Función para verificar si una columna existe en una tabla
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

ticketsWhatsappEnsureSchema($conn);

// Obtener empresa del usuario actual
$user_empresa_id = getCurrentUserEmpresaId();

// Filtrar configuraciones por empresa del usuario (solo si el campo existe)
$has_categories_empresa = columnExists($conn, 'categories', 'id_empresa');
$categories_query = "SELECT id, name FROM categories";
$categories_params = [];
$categories_types = '';
if ($has_categories_empresa && $user_empresa_id !== null) {
    $categories_query .= " WHERE id_empresa = ?";
    $categories_params[] = $user_empresa_id;
    $categories_types = 'i';
}
$categories_query .= " ORDER BY name";
if (!empty($categories_params)) {
    $categories_stmt = $conn->prepare($categories_query);
    $categories_stmt->bind_param($categories_types, ...$categories_params);
    $categories_stmt->execute();
    $categories = $categories_stmt->get_result();
} else {
    $categories = $conn->query($categories_query);
}

$has_statuses_empresa = columnExists($conn, 'statuses', 'id_empresa');
$statuses_query = "SELECT id, name FROM statuses";
$statuses_params = [];
$statuses_types = '';
// Para statuses, mostrar siempre los predeterminados (1, 2, 3, 10) y los de la empresa del usuario
if ($has_statuses_empresa && $user_empresa_id !== null) {
    $statuses_query .= " WHERE (id IN (1, 2, 3, 10) OR id_empresa = ?)";
    $statuses_params[] = $user_empresa_id;
    $statuses_types = 'i';
}
$statuses_query .= " ORDER BY name";
if (!empty($statuses_params)) {
    $statuses_stmt = $conn->prepare($statuses_query);
    $statuses_stmt->bind_param($statuses_types, ...$statuses_params);
    $statuses_stmt->execute();
    $statuses = $statuses_stmt->get_result();
} else {
    $statuses = $conn->query($statuses_query);
}

$has_contact_modes_empresa = columnExists($conn, 'contact_modes', 'id_empresa');
$contact_modes_query = "SELECT id, name FROM contact_modes";
$contact_modes_params = [];
$contact_modes_types = '';
if ($has_contact_modes_empresa && $user_empresa_id !== null) {
    $contact_modes_query .= " WHERE id_empresa = ?";
    $contact_modes_params[] = $user_empresa_id;
    $contact_modes_types = 'i';
}
$contact_modes_query .= " ORDER BY name";
if (!empty($contact_modes_params)) {
    $contact_modes_stmt = $conn->prepare($contact_modes_query);
    $contact_modes_stmt->bind_param($contact_modes_types, ...$contact_modes_params);
    $contact_modes_stmt->execute();
    $contact_modes = $contact_modes_stmt->get_result();
} else {
    $contact_modes = $conn->query($contact_modes_query);
}

$has_levels_empresa = columnExists($conn, 'levels', 'id_empresa');
$levels_query = "SELECT id, name FROM levels";
$levels_params = [];
$levels_types = '';
if ($has_levels_empresa && $user_empresa_id !== null) {
    $levels_query .= " WHERE id_empresa = ?";
    $levels_params[] = $user_empresa_id;
    $levels_types = 'i';
}
$levels_query .= " ORDER BY name";
if (!empty($levels_params)) {
    $levels_stmt = $conn->prepare($levels_query);
    $levels_stmt->bind_param($levels_types, ...$levels_params);
    $levels_stmt->execute();
    $levels = $levels_stmt->get_result();
} else {
    $levels = $conn->query($levels_query);
}

$has_impacts_empresa = columnExists($conn, 'impacts', 'id_empresa');
$impacts_query = "SELECT id, name FROM impacts";
$impacts_params = [];
$impacts_types = '';
if ($has_impacts_empresa && $user_empresa_id !== null) {
    $impacts_query .= " WHERE id_empresa = ?";
    $impacts_params[] = $user_empresa_id;
    $impacts_types = 'i';
}
$impacts_query .= " ORDER BY name";
if (!empty($impacts_params)) {
    $impacts_stmt = $conn->prepare($impacts_query);
    $impacts_stmt->bind_param($impacts_types, ...$impacts_params);
    $impacts_stmt->execute();
    $impacts = $impacts_stmt->get_result();
} else {
    $impacts = $conn->query($impacts_query);
}

$has_urgencies_empresa = columnExists($conn, 'urgencies', 'id_empresa');
$urgencies_query = "SELECT id, name FROM urgencies";
$urgencies_params = [];
$urgencies_types = '';
if ($has_urgencies_empresa && $user_empresa_id !== null) {
    $urgencies_query .= " WHERE id_empresa = ?";
    $urgencies_params[] = $user_empresa_id;
    $urgencies_types = 'i';
}
$urgencies_query .= " ORDER BY name";
if (!empty($urgencies_params)) {
    $urgencies_stmt = $conn->prepare($urgencies_query);
    $urgencies_stmt->bind_param($urgencies_types, ...$urgencies_params);
    $urgencies_stmt->execute();
    $urgencies = $urgencies_stmt->get_result();
} else {
    $urgencies = $conn->query($urgencies_query);
}

$has_priorities_empresa = columnExists($conn, 'priorities', 'id_empresa');
$priorities_query = "SELECT id, name FROM priorities";
$priorities_params = [];
$priorities_types = '';
if ($has_priorities_empresa && $user_empresa_id !== null) {
    $priorities_query .= " WHERE id_empresa = ?";
    $priorities_params[] = $user_empresa_id;
    $priorities_types = 'i';
}
$priorities_query .= " ORDER BY name";
if (!empty($priorities_params)) {
    $priorities_stmt = $conn->prepare($priorities_query);
    $priorities_stmt->bind_param($priorities_types, ...$priorities_params);
    $priorities_stmt->execute();
    $priorities = $priorities_stmt->get_result();
} else {
    $priorities = $conn->query($priorities_query);
}

$has_classifications_empresa = columnExists($conn, 'classifications', 'id_empresa');
$classifications_query = "SELECT id, name FROM classifications";
$classifications_params = [];
$classifications_types = '';
if ($has_classifications_empresa && $user_empresa_id !== null) {
    $classifications_query .= " WHERE id_empresa = ?";
    $classifications_params[] = $user_empresa_id;
    $classifications_types = 'i';
}
$classifications_query .= " ORDER BY name";
if (!empty($classifications_params)) {
    $classifications_stmt = $conn->prepare($classifications_query);
    $classifications_stmt->bind_param($classifications_types, ...$classifications_params);
    $classifications_stmt->execute();
    $classifications = $classifications_stmt->get_result();
} else {
    $classifications = $conn->query($classifications_query);
}

// Requesters y technicians ya tienen filtros de empresa aplicados
$requesters_query = "SELECT r.id, r.name, e.nombre as company_name FROM requesters r LEFT JOIN empresas e ON r.company_id = e.id_empresa";
$requesters_params = [];
$requesters_types = '';
$requesters_query = addEmpresaFilter($requesters_query, 'r.id_empresa', $requesters_params, $requesters_types);
$requesters_query .= " ORDER BY r.name";
$requesters_stmt = $conn->prepare($requesters_query);
if (!empty($requesters_params)) {
    $requesters_stmt->bind_param($requesters_types, ...$requesters_params);
}
$requesters_stmt->execute();
$requesters = $requesters_stmt->get_result();

$technicians_query = "SELECT id, name FROM technicians";
$technicians_params = [];
$technicians_types = '';
$technicians_query = addEmpresaFilter($technicians_query, 'id_empresa', $technicians_params, $technicians_types);
$technicians_query .= " ORDER BY name";
$technicians_stmt = $conn->prepare($technicians_query);
if (!empty($technicians_params)) {
    $technicians_stmt->bind_param($technicians_types, ...$technicians_params);
}
$technicians_stmt->execute();
$technicians = $technicians_stmt->get_result();

// Función para enviar correo de notificación de ticket cerrado
function sendTicketClosedNotification($ticket_id, $ticket_number, $requester_email, $requester_name, $ticket_description, $resolution, $technician_name = null, $technician_email = null, $id_empresa = null) {
    global $conn;
    try {
        $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
        if (!$empresa_config) {
            throw new Exception('No hay configuración de correo activa para esta empresa.');
        }

        $mail = createMailerForEmpresa($id_empresa, $conn);
        
        // Configurar destinatario
        $mail->addAddress($requester_email, $requester_name);
        
        // Agregar copia al técnico asignado si existe
        if (!empty($technician_email)) {
            $mail->addCC($technician_email, $technician_name);
        }
        
        // Configurar asunto
        $mail->Subject = "[Ticket #$ticket_number] Ticket cerrado - Sistema de Tickets";
        
        // Diseño del correo de cierre
        $email_body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Ticket Cerrado #$ticket_number</title>
            </head>
            <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                    <!-- Header del correo -->
                    <div style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 30px; text-align: center; color: white;'>
                        <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>Sistema de Tickets</h2>
                        <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Ticket #$ticket_number - Cerrado</p>
                    </div>
                    
                    <!-- Contenido principal -->
                    <div style='padding: 40px 30px;'>
                        <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                            Hola <strong>$requester_name</strong>,
                        </p>
                        
                        <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                            Le informamos que su ticket ha sido <strong>cerrado exitosamente.</strong> 
                        </p>
                        
                        <!-- Información del ticket -->
                        <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; border-left: 5px solid #dc3545; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                            <h3 style='margin: 0 0 15px 0; color: #2c3e50; font-size: 18px;'>Descripción original del problema:</h3>
                            <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 20px;'>
                                " . nl2br(htmlspecialchars($ticket_description)) . "
                            </div>
                            
                            
                            </div>";
                            
        if ($technician_name) {
            $email_body .= "
                            <p style='margin: 10px 0 0 0; font-size: 14px; color: #6c757d;'>
                                <strong>Atendido por:</strong> $technician_name
                            </p>";
        }
        
        $email_body .= "
                        </div>
                        
                        <!-- Información de contacto para dudas -->
                        <div style='background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #2196f3;'>
                            <h4 style='margin: 0 0 10px 0; color: #1976d2; font-size: 16px;'>
                                <i style='margin-right: 8px;'>💬</i> ¿Tiene alguna duda o necesita aclaración?
                            </h4>
                            <p style='margin: 0; color: #424242; font-size: 14px;'>
                                Si necesita información adicional o tiene alguna pregunta sobre la resolución, puede:
                            </p>
                            <ul style='margin: 10px 0 0 20px; color: #424242; font-size: 14px;'>
                                <li><strong>Abrir un nuevo ticket</strong> desde el sistema o enviando un correo nuevo a la bandeja de soporte</li>
                                <li><strong>Contactarnos</strong> a través de nuestros canales de soporte habituales</li>
                            </ul>
                        </div>
                        
                        <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
                            Gracias por utilizar nuestro sistema de soporte técnico.
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                        <div style='color: #6c757d; font-size: 12px;'>
                            <p style='margin: 0 0 10px 0;'>
                                <strong>GK</strong> 
                            </p>
                            <p style='margin: 0; opacity: 0.8;'>
                                Este es un mensaje automático de cierre. <strong>No responda a este correo</strong>;
                                las respuestas a esta dirección no serán atendidas.
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
        
        $mail->Body = $email_body;
        $mail->AltBody = strip_tags($email_body);
        
        applyNoreplyMailHeaders($mail, $empresa_config, $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets');
        
        // Enviar el correo
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        error_log("Correo de notificación de ticket cerrado enviado exitosamente a: " . $requester_email . " para ticket ID: " . $ticket_id);
        return [
            'success' => true,
            'message' => 'Correo de notificación de cierre enviado exitosamente'
        ];
    } catch (Exception $e) {
        error_log("ERROR - Fallo al enviar correo de notificación de ticket cerrado para ticket ID " . $ticket_id . ": " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al enviar el correo de notificación: ' . $e->getMessage()
        ];
    }
}

// Función para enviar correo de notificación de ticket cerrado por inactividad
function sendTicketClosedByInactivityNotification($ticket_id, $ticket_number, $requester_email, $requester_name, $ticket_description, $technician_name = null, $technician_email = null, $id_empresa = null) {
    global $conn;
    try {
        $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
        if (!$empresa_config) {
            throw new Exception('No hay configuración de correo activa para esta empresa.');
        }

        $mail = createMailerForEmpresa($id_empresa, $conn);
        
        // Configurar destinatario
        $mail->addAddress($requester_email, $requester_name);
        
        // Agregar copia al técnico asignado si existe
        if (!empty($technician_email)) {
            $mail->addCC($technician_email, $technician_name);
        }
        
        // Configurar asunto
        $mail->Subject = "[Ticket #$ticket_number] Ticket cerrado por inactividad - Sistema de Tickets";
        
        // Diseño del correo de cierre por inactividad
        $email_body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Ticket Cerrado por Inactividad #$ticket_number</title>
            </head>
            <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                    <!-- Header del correo -->
                    <div style='background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); padding: 30px; text-align: center; color: white;'>
                        <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>Sistema de Tickets</h2>
                        <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Ticket #$ticket_number - Cerrado por Inactividad</p>
                    </div>
                    
                    <!-- Contenido principal -->
                    <div style='padding: 40px 30px;'>
                        <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                            Hola <strong>$requester_name</strong>,
                        </p>
                        
                        <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                            Le informamos que su ticket ha sido <strong>cerrado por inactividad</strong> debido a la falta de respuesta o actividad durante un período prolongado.
                        </p>
                        
                        <!-- Información del ticket -->
                        <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 25px; border-radius: 12px; border-left: 5px solid #ffc107; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                            <h3 style='margin: 0 0 15px 0; color: #2c3e50; font-size: 18px;'>Descripción original del problema:</h3>
                            <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 20px;'>
                                " . nl2br(htmlspecialchars($ticket_description)) . "
                            </div>";
                            
        if ($technician_name) {
            $email_body .= "
                            <p style='margin: 10px 0 0 0; font-size: 14px; color: #6c757d;'>
                                <strong>Último técnico asignado:</strong> $technician_name
                            </p>";
        }
        
        $email_body .= "
                        </div>
                        
                        <!-- Motivo del cierre por inactividad -->
                        <div style='background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #dc3545;'>
                            <h4 style='margin: 0 0 10px 0; color: #721c24; font-size: 16px;'>
                                <i style='margin-right: 8px;'>⏰</i> ¿Por qué se cerró por inactividad?
                            </h4>
                            <p style='margin: 0; color: #721c24; font-size: 14px;'>
                                Este ticket se cerró automáticamente debido a que no se registró actividad reciente. Esto puede ocurrir cuando:
                            </p>
                            <ul style='margin: 10px 0 0 20px; color: #721c24; font-size: 14px;'>
                                <li>No se recibió respuesta del solicitante durante un período prolongado</li>
                                <li>El problema fue resuelto pero no se confirmó el cierre</li>
                                <li>No hubo seguimiento por parte del usuario</li>
                            </ul>
                        </div>
                        
                        <!-- ¿Necesita reabrir el ticket? -->
                        <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #28a745;'>
                            <h4 style='margin: 0 0 10px 0; color: #155724; font-size: 16px;'>
                                <i style='margin-right: 8px;'>🔄</i> ¿El problema persiste o necesita ayuda adicional?
                            </h4>
                            <p style='margin: 0; color: #155724; font-size: 14px;'>
                                Si el problema no ha sido resuelto o necesita asistencia adicional, puede:
                            </p>
                            <ul style='margin: 10px 0 0 20px; color: #155724; font-size: 14px;'>
                                <li><strong>Abrir un nuevo ticket</strong> desde el sistema o enviando un correo nuevo a la bandeja de soporte</li>
                                <li><strong>Contactarnos</strong> a través de nuestros canales de soporte habituales</li>
                            </ul>
                        </div>
                        
                        <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
                            Lamentamos cualquier inconveniente. Nuestro equipo está siempre disponible para ayudarle.
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                        <div style='color: #6c757d; font-size: 12px;'>
                            <p style='margin: 0 0 10px 0;'>
                                <strong>GK</strong> 
                            </p>
                            <p style='margin: 0; opacity: 0.8;'>
                                Este es un mensaje automático de cierre. <strong>No responda a este correo</strong>;
                                las respuestas a esta dirección no serán atendidas.
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
        
        $mail->Body = $email_body;
        $mail->AltBody = strip_tags($email_body);
        
        applyNoreplyMailHeaders($mail, $empresa_config, $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets');
        
        // Enviar el correo
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        error_log("Correo de notificación de ticket cerrado por inactividad enviado exitosamente a: " . $requester_email . " para ticket ID: " . $ticket_id);
        return [
            'success' => true,
            'message' => 'Correo de notificación de cierre por inactividad enviado exitosamente'
        ];
    } catch (Exception $e) {
        error_log("ERROR - Fallo al enviar correo de notificación de ticket cerrado por inactividad para ticket ID " . $ticket_id . ": " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al enviar el correo de notificación: ' . $e->getMessage()
        ];
    }
}

// Función para enviar correo de notificación cuando el ticket pasa de status 1 (Sin Asignar) a otro status (siendo atendido)
function sendTicketBeingAttendedNotification($ticket_id, $ticket_number, $requester_email, $requester_name, $ticket_description, $new_status_name, $technician_name = null, $technician_email = null, $id_empresa = null) {
    global $conn;
    try {
        $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
        if (!$empresa_config) {
            throw new Exception('No hay configuración de correo activa para esta empresa.');
        }

        $mail = createMailerForEmpresa($id_empresa, $conn);
        
        // Configurar destinatario
        $mail->addAddress($requester_email, $requester_name);
        
        // Agregar copia al técnico asignado si existe
        if (!empty($technician_email)) {
            $mail->addCC($technician_email, $technician_name);
        }
        
        // Configurar asunto
        $mail->Subject = "[Ticket #$ticket_number] Su ticket está siendo atendido - Sistema de Tickets";
        
        // Diseño del correo de ticket siendo atendido
        $email_body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Ticket siendo atendido #$ticket_number</title>
            </head>
            <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                    <!-- Header del correo -->
                    <div style='background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); padding: 30px; text-align: center; color: white;'>
                        <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>Sistema de Tickets</h2>
                        <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Ticket #$ticket_number - En Atención</p>
                    </div>
                    
                    <!-- Contenido principal -->
                    <div style='padding: 40px 30px;'>
                        <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                            Hola <strong>$requester_name</strong>,
                        </p>
                        
                        <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                            Le informamos que su ticket está siendo <strong>atendido</strong> por nuestro equipo de soporte técnico.
                        </p>
                        
                        <!-- Información del ticket -->
                        <div style='background: linear-gradient(135deg, #e7f3ff 0%, #cfe2ff 100%); padding: 25px; border-radius: 12px; border-left: 5px solid #007bff; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                            <h3 style='margin: 0 0 15px 0; color: #2c3e50; font-size: 18px;'>Estado actual:</h3>
                            <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 20px;'>
                                <p style='margin: 0; font-size: 16px; color: #007bff; font-weight: 600;'>
                                    <i style='margin-right: 8px;'>📋</i> $new_status_name
                                </p>
                            </div>
                            
                            <h3 style='margin: 15px 0 15px 0; color: #2c3e50; font-size: 18px;'>Descripción del problema:</h3>
                            <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 15px;'>
                                " . nl2br(htmlspecialchars($ticket_description)) . "
                            </div>";
                            
        if ($technician_name) {
            $email_body .= "
                            <p style='margin: 10px 0 0 0; font-size: 14px; color: #6c757d;'>
                                <strong>Técnico asignado:</strong> $technician_name
                            </p>";
        }
        
        $email_body .= "
                        </div>
                        
                        <!-- Información de seguimiento -->
                        <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #28a745;'>
                            <h4 style='margin: 0 0 10px 0; color: #155724; font-size: 16px;'>
                                <i style='margin-right: 8px;'>✅</i> ¿Qué significa esto?
                            </h4>
                            <p style='margin: 0; color: #155724; font-size: 14px;'>
                                Su ticket ha sido tomado en cuenta y está siendo procesado por nuestro equipo. Recibirá actualizaciones sobre el progreso de su solicitud.
                            </p>
                        </div>
                        
                        <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
                            Recibirá notificaciones sobre las actualizaciones de este ticket. Si tiene alguna pregunta adicional, puede responder directamente a este correo.
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                        <div style='color: #6c757d; font-size: 12px;'>
                            <p style='margin: 0 0 10px 0;'>
                                <strong>GK</strong> 
                            </p>
                            <p style='margin: 0; opacity: 0.8;'>
                                Este es un mensaje automático del sistema de tickets. 
                                Para continuar la conversación, responda directamente a este correo.
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
        
        $mail->Body = $email_body;
        $mail->AltBody = strip_tags($email_body);
        
        // Configurar Reply-To para que las respuestas lleguen al sistema
        $mail->addReplyTo($empresa_config['smtp_from_email'], $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets');
        
        // Enviar el correo
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        error_log("Correo de notificación de ticket siendo atendido enviado exitosamente a: " . $requester_email . " para ticket ID: " . $ticket_id);
        return [
            'success' => true,
            'message' => 'Correo de notificación de ticket siendo atendido enviado exitosamente'
        ];
    } catch (Exception $e) {
        error_log("ERROR - Fallo al enviar correo de notificación de ticket siendo atendido para ticket ID " . $ticket_id . ": " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al enviar el correo de notificación: ' . $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : NULL;
    $technician_id = !empty($_POST['technician_id']) ? $_POST['technician_id'] : NULL;
    if ($technician_id) {
        $status_id = 2;
    } else {
        $status_id = 1;
    }
    $status_id = !empty($_POST['status_id']) ? $_POST['status_id'] : $status_id;
    $contact_mode_id = !empty($_POST['contact_mode_id']) ? $_POST['contact_mode_id'] : NULL;
    $level_id = !empty($_POST['level_id']) ? $_POST['level_id'] : NULL;
    $impact_id = !empty($_POST['impact_id']) ? $_POST['impact_id'] : NULL;
    $urgency_id = !empty($_POST['urgency_id']) ? $_POST['urgency_id'] : NULL;
    $priority_id = !empty($_POST['priority_id']) ? $_POST['priority_id'] : NULL;
    $impact_details = $_POST['impact_details'] ?? null;
    $requester_id = !empty($_POST['requester_id']) ? $_POST['requester_id'] : NULL;
    $classification_id = !empty($_POST['classification_id']) ? $_POST['classification_id'] : NULL;
    $technician_id = !empty($_POST['technician_id']) ? $_POST['technician_id'] : NULL;
    $description = $_POST['description'];
    $resolution = $_POST['resolution'];

    $closed_at = NULL;
    $closed_by_inactivity = false;
    
    if (isset($_POST['close_ticket']) && $_POST['close_ticket'] == 1) {
        $is_inactivity_close = isset($_POST['close_by_inactivity']) && $_POST['close_by_inactivity'] == 1;
        if (empty($_POST['resolution']) && !$is_inactivity_close) {
            $message = "No puedes cerrar el ticket sin una resolución.";
            $messageType = "danger";
        } else {
            $closed_at = 'CURRENT_TIMESTAMP';
            $status_id = 3;

            if ($is_inactivity_close) {
                $closed_by_inactivity = true;
                if (trim((string) $resolution) === '') {
                    $resolution = 'Ticket cerrado automáticamente por inactividad.';
                }
            }
        }
    }
    
    if (empty($description)) {
        $message = "La descripción del ticket es obligatoria";
        $messageType = "danger";
    } else {
        // Obtener los valores actuales del ticket para comparar
        $query_current = "SELECT * FROM tickets WHERE id = ?";
        $params_current = [$id];
        $types_current = 'i';
        $query_current = addEmpresaFilter($query_current, 'id_empresa', $params_current, $types_current);
        $current_ticket = $conn->prepare($query_current);
        $current_ticket->bind_param($types_current, ...$params_current);
        $current_ticket->execute();
        $current_data = $current_ticket->get_result()->fetch_assoc();
        $current_ticket->close();
        
        // Verificar que el ticket pertenece a la empresa del usuario
        if (!$current_data) {
            header('Location: tickets.php?error=no_access');
            exit;
        }
        
        $user_name = getUserName();
        $changes_made = false;
        
        // Construir la consulta dinámicamente para manejar CURRENT_TIMESTAMP
        $update_fields = [];
        $params = [];
        $types = "";
        
        // Verificar cambios en cada campo y registrar en historial
        if ($category_id !== null && $category_id != $current_data['category_id']) {
            $update_fields[] = "category_id = ?";
            $params[] = $category_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'category_id', $current_data['category_id'], $category_id);
            $changes_made = true;
        }
        
        // Variable para detectar si el ticket pasa de status 1 a otro (siendo atendido)
        $ticket_being_attended = false;
        $new_status_id_for_email = null;
        
        if ($status_id !== null && $status_id != $current_data['status_id']) {
            $update_fields[] = "status_id = ?";
            $params[] = $status_id;
            $types .= "i";

            if ($closed_by_inactivity && (int) $status_id === 3) {
                $old_status_name = getFieldName($conn, 'status_id', $current_data['status_id']) ?: $current_data['status_id'];
                logTicketHistory(
                    $conn,
                    $id,
                    'updated',
                    $user_name,
                    'Estado',
                    $old_status_name,
                    'Cerrado por inactividad',
                    "Campo 'Estado' actualizado — cierre por inactividad"
                );
            } else {
                logFieldUpdate($conn, $id, $user_name, 'status_id', $current_data['status_id'], $status_id);
            }
            
            // Verificar si el ticket pasa de status 1 (Sin Asignar) a otro status
            // Excluir cuando el nuevo status es 3 (Cerrado), ya que tiene su propio correo de notificación
            if ($current_data['status_id'] == 1 && $status_id != 1 && $status_id != 3) {
                $ticket_being_attended = true;
                $new_status_id_for_email = $status_id;
            }
            
            // Manejar tracking de status específico (status_id = 10)
            if ($status_id == 10) {
                // Iniciar tracking para status 10
                startStatusTracking($conn, $id, 10);
            } elseif ($current_data['status_id'] == 10) {
                // Si estaba en status 10 y cambió, terminar el tracking
                endStatusTracking($conn, $id);
            }
            
            $changes_made = true;
        }
        
        if ($contact_mode_id !== null && $contact_mode_id != $current_data['contact_mode_id']) {
            $update_fields[] = "contact_mode_id = ?";
            $params[] = $contact_mode_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'contact_mode_id', $current_data['contact_mode_id'], $contact_mode_id);
            $changes_made = true;
        }
        
        if ($level_id !== null && $level_id != $current_data['level_id']) {
            $update_fields[] = "level_id = ?";
            $params[] = $level_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'level_id', $current_data['level_id'], $level_id);
            $changes_made = true;
        }
        
        if ($impact_id !== null && $impact_id != $current_data['impact_id']) {
            $update_fields[] = "impact_id = ?";
            $params[] = $impact_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'impact_id', $current_data['impact_id'], $impact_id);
            $changes_made = true;
        }
        
        if ($urgency_id !== null && $urgency_id != $current_data['urgency_id']) {
            $update_fields[] = "urgency_id = ?";
            $params[] = $urgency_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'urgency_id', $current_data['urgency_id'], $urgency_id);
            $changes_made = true;
        }
        
        if ($priority_id !== null && $priority_id != $current_data['priority_id']) {
            $update_fields[] = "priority_id = ?";
            $params[] = $priority_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'priority_id', $current_data['priority_id'], $priority_id);
            $changes_made = true;
        }
        
        if ($impact_details !== null && $impact_details != $current_data['impact_details']) {
            $update_fields[] = "impact_details = ?";
            $params[] = $impact_details;
            $types .= "s";
            logFieldUpdate($conn, $id, $user_name, 'impact_details', $current_data['impact_details'], $impact_details);
            $changes_made = true;
        }
        
        if ($requester_id !== null && $requester_id != $current_data['requester_id']) {
            $update_fields[] = "requester_id = ?";
            $params[] = $requester_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'requester_id', $current_data['requester_id'], $requester_id);
            $changes_made = true;
        }
        
        if ($classification_id !== null && $classification_id != $current_data['classification_id']) {
            $update_fields[] = "classification_id = ?";
            $params[] = $classification_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'classification_id', $current_data['classification_id'], $classification_id);
            $changes_made = true;
        }
        
        if ($technician_id !== null && $technician_id != $current_data['technician_id']) {
            $update_fields[] = "technician_id = ?";
            $params[] = $technician_id;
            $types .= "i";
            logFieldUpdate($conn, $id, $user_name, 'technician_id', $current_data['technician_id'], $technician_id);
            $changes_made = true;
        }
        
        if ($description !== null && $description != $current_data['description']) {
            $update_fields[] = "description = ?";
            $params[] = $description;
            $types .= "s";
            logFieldUpdate($conn, $id, $user_name, 'description', $current_data['description'], $description);
            $changes_made = true;
        }
        
        if ($resolution !== null && $resolution != $current_data['resolution']) {
            $update_fields[] = "resolution = ?";
            $params[] = $resolution;
            $types .= "s";
            logFieldUpdate($conn, $id, $user_name, 'resolution', $current_data['resolution'], $resolution);
            $changes_made = true;
        }
        
        // Verificar si se está cerrando o reabriendo el ticket
        $ticket_being_closed = false;
        $wants_closed = isset($_POST['close_ticket']) && (int) $_POST['close_ticket'] === 1;
        $was_closed = !empty($current_data['closed_at']);

        if ($wants_closed && !$was_closed) {
            // Solo cerrar si el ticket aún no estaba cerrado (evita re-fechar / re-notificar al guardar)
            if ($closed_at === 'CURRENT_TIMESTAMP') {
                $update_fields[] = "closed_at = CURRENT_TIMESTAMP";
                logTicketClosed($conn, $id, $user_name, $resolution, $closed_by_inactivity);
                $changes_made = true;
                $ticket_being_closed = true;
            } else if ($closed_at !== null) {
                $update_fields[] = "closed_at = ?";
                $params[] = $closed_at;
                $types .= "s";
                logTicketClosed($conn, $id, $user_name, $resolution, $closed_by_inactivity);
                $changes_made = true;
                $ticket_being_closed = true;
            }
        } else if (!$wants_closed && $was_closed) {
            // Desmarcar "Marcar como cerrado": limpiar closed_at para reabrir el ticket
            $update_fields[] = "closed_at = NULL";
            logTicketReopened($conn, $id, $user_name);
            $changes_made = true;
        }

        if (columnExists($conn, 'tickets', 'closed_by_inactivity')) {
            // Solo aplica si el ticket queda cerrado tras guardar
            $is_closed_after_save = $wants_closed && ($ticket_being_closed || $was_closed);

            $new_inactivity = 0;
            if ($is_closed_after_save && isset($_POST['close_by_inactivity']) && (int) $_POST['close_by_inactivity'] === 1) {
                $new_inactivity = 1;
            }

            if ((int) ($current_data['closed_by_inactivity'] ?? 0) !== $new_inactivity) {
                $update_fields[] = 'closed_by_inactivity = ?';
                $params[] = $new_inactivity;
                $types .= 'i';
                $changes_made = true;
            }
        }

        if (columnExists($conn, 'tickets', 'whatsapp_enabled')) {
            $new_whatsapp_enabled = (isset($_POST['whatsapp_enabled']) && (int) $_POST['whatsapp_enabled'] === 1) ? 1 : 0;
            if ((int) ($current_data['whatsapp_enabled'] ?? 0) !== $new_whatsapp_enabled) {
                $update_fields[] = 'whatsapp_enabled = ?';
                $params[] = $new_whatsapp_enabled;
                $types .= 'i';
                logFieldUpdate($conn, $id, $user_name, 'whatsapp_enabled', $current_data['whatsapp_enabled'] ?? 0, $new_whatsapp_enabled);
                $changes_made = true;
            }
        }
        
        // Verificar si hay archivos adjuntos, costos o motivos de actualización
        $has_attachments = !empty($_FILES['attachments']['name'][0]);
        $has_costs = !empty($_POST['cost_description']) && !empty($_POST['cost_amount']);
        $has_update_reasons = !empty($_POST['update_reasons']) && is_array($_POST['update_reasons']) && !empty(array_filter($_POST['update_reasons']));
        
        // Solo actualizar si hay cambios en campos del ticket
        if (!empty($update_fields)) {
            // Agregar el ID para la cláusula WHERE
            $params[] = $id;
            $types .= "i";
            
            // Construir la consulta completa
            $query = "UPDATE tickets SET " . implode(", ", $update_fields) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Si se cerró el ticket, enviar correo de notificación
                if ($ticket_being_closed) {
                    // Obtener información del solicitante y técnico para el correo
                    $email_query = $conn->prepare("
                        SELECT r.name as requester_name, r.email as requester_email, 
                               t.name as technician_name, t.email as technician_email, 
                               tickets.description, tickets.ticket_number, tickets.id_empresa
                        FROM tickets 
                        LEFT JOIN requesters r ON tickets.requester_id = r.id
                        LEFT JOIN technicians t ON tickets.technician_id = t.id
                        WHERE tickets.id = ?
                    ");
                    $email_query->bind_param("i", $id);
                    $email_query->execute();
                    $email_result = $email_query->get_result();
                    $email_data = $email_result->fetch_assoc();
                    $email_query->close();
                    
                    if ($email_data && !empty($email_data['requester_email'])) {
                        // Enviar el correo apropiado según el tipo de cierre
                        if ($closed_by_inactivity) {
                            $email_result = sendTicketClosedByInactivityNotification(
                                $id,
                                $email_data['ticket_number'],
                                $email_data['requester_email'],
                                $email_data['requester_name'],
                                $email_data['description'],
                                $email_data['technician_name'],
                                $email_data['technician_email'],
                                $email_data['id_empresa'] ?? null
                            );
                        } else {
                            $email_result = sendTicketClosedNotification(
                                $id,
                                $email_data['ticket_number'],
                                $email_data['requester_email'],
                                $email_data['requester_name'],
                                $email_data['description'],
                                $resolution,
                                $email_data['technician_name'],
                                $email_data['technician_email'],
                                $email_data['id_empresa'] ?? null
                            );
                        }
                        
                        if (!$email_result['success']) {
                            error_log("ADVERTENCIA - No se pudo enviar correo de cierre para ticket #$id: " . $email_result['message']);
                        }
                    }

                    if (!empty($email_data['ticket_number'])) {
                        ticketsWhatsappNotifyTicketClosed($conn, (int) $id, (string) $email_data['ticket_number']);
                    }
                }
                
                // Si el ticket pasa de status 1 a otro, enviar correo de notificación
                if ($ticket_being_attended && $new_status_id_for_email !== null) {
                    // Obtener información del solicitante y técnico para el correo
                    $email_query = $conn->prepare("
                        SELECT r.name as requester_name, r.email as requester_email, 
                               t.name as technician_name, t.email as technician_email, 
                               tickets.description, tickets.ticket_number, tickets.id_empresa
                        FROM tickets 
                        LEFT JOIN requesters r ON tickets.requester_id = r.id
                        LEFT JOIN technicians t ON tickets.technician_id = t.id
                        WHERE tickets.id = ?
                    ");
                    $email_query->bind_param("i", $id);
                    $email_query->execute();
                    $email_result = $email_query->get_result();
                    $email_data = $email_result->fetch_assoc();
                    $email_query->close();
                    
                    // Obtener el nombre del nuevo status
                    $status_query = $conn->prepare("SELECT name FROM statuses WHERE id = ?");
                    $status_query->bind_param("i", $new_status_id_for_email);
                    $status_query->execute();
                    $status_result = $status_query->get_result();
                    $status_data = $status_result->fetch_assoc();
                    $status_query->close();
                    $new_status_name = $status_data ? $status_data['name'] : 'En Atención';
                    
                    if ($email_data && !empty($email_data['requester_email'])) {
                        $email_result = sendTicketBeingAttendedNotification(
                            $id,
                            $email_data['ticket_number'],
                            $email_data['requester_email'],
                            $email_data['requester_name'],
                            $email_data['description'],
                            $new_status_name,
                            $email_data['technician_name'],
                            $email_data['technician_email'],
                            $email_data['id_empresa'] ?? null
                        );
                        
                        if (!$email_result['success']) {
                            error_log("ADVERTENCIA - No se pudo enviar correo de ticket siendo atendido para ticket #$id: " . $email_result['message']);
                        }
                    }
                }
                
                $message = "Ticket actualizado correctamente";
                $messageType = "success";
            } else {
                $message = "Error al actualizar el ticket: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
        
        // Procesar archivos adjuntos (independientemente de si hubo otros cambios)
        if ($has_attachments) {
                $upload_dir = '../../uploads/';
            
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_count = count($_FILES['attachments']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $name = basename($_FILES['attachments']['name'][$i]);
                        $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                        $file_type = $_FILES['attachments']['type'][$i];
                        $file_size = $_FILES['attachments']['size'][$i];
                        // Generar nombre único para el archivo
                        $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                        $unique_filename = uniqid() . '_' . $name;
                        
                        // Crear directorio si no existe
                        $year = date('Y');
                        $month = date('m');
                        $upload_path = $upload_dir . 'attachments/' . $year . '/' . $month . '/';
                        if (!file_exists($upload_path)) {
                            mkdir($upload_path, 0777, true);
                        }
                        
                        $file_path = $upload_path . $unique_filename;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            // Guardar ruta relativa sin el ../../ al inicio (para que sea relativa desde tickets/)
                            $relative_path = 'uploads/attachments/' . $year . '/' . $month . '/' . $unique_filename;
                            
                            $file_stmt = $conn->prepare("INSERT INTO ticket_attachments (ticket_id, message_id, ticket_message_id, filename, file_name, file_path, mime_type, size, is_inline, created_at) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, 0, NOW())");
                            if (!$file_stmt) {
                                error_log("Error en la preparación de la consulta: " . $conn->error);
                                $message = "Error al preparar la consulta para el archivo adjunto";
                                $messageType = "danger";
                                continue;
                            }
                            $file_stmt->bind_param("issssi", $id, $name, $name, $relative_path, $file_type, $file_size);
                            if (!$file_stmt->execute()) {
                                error_log("Error al guardar el archivo adjunto: " . $file_stmt->error);
                                $message = "Error al guardar el archivo en la base de datos";
                                $messageType = "danger";
                            } else {
                                error_log("DEBUG - Archivo guardado en edit: $name en $relative_path (ID: " . $file_stmt->insert_id . ")");
                            }
                            $file_stmt->close();
                        } else {
                            error_log("Error al mover el archivo subido");
                            $message = "Error al guardar el archivo en el servidor";
                            $messageType = "danger";
                        }
                    }
                }
                
                // Si solo se subieron archivos sin otros cambios, mostrar mensaje de éxito
                if (empty($update_fields) && !$has_costs && !$has_update_reasons) {
                    $message = "Archivo(s) adjunto(s) agregado(s) correctamente";
                    $messageType = "success";
                } elseif (!empty($update_fields)) {
                    // Si ya había mensaje de éxito por otros cambios, no sobrescribirlo
                    if (empty($message)) {
                        $message = "Ticket actualizado correctamente";
                        $messageType = "success";
                    }
                }
        }
        
        // Procesar costos (independientemente de si hubo otros cambios)
        if ($has_costs) {
            $cost_description = $_POST['cost_description'];
            $cost_amount = $_POST['cost_amount'];
            $cost_file_name = null;
            $cost_file_content = null;
            if (isset($_FILES['cost_file']) && $_FILES['cost_file']['error'] === UPLOAD_ERR_OK) {
                $cost_file_name = basename($_FILES['cost_file']['name']);
                $cost_file_content = file_get_contents($_FILES['cost_file']['tmp_name']);
            }
            $cost_stmt = $conn->prepare("INSERT INTO ticket_costs (ticket_id, description, amount, file_name, file_content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $cost_stmt->bind_param("isdss", $id, $cost_description, $cost_amount, $cost_file_name, $cost_file_content);
            $cost_stmt->execute();
            $cost_stmt->close();
            
            // Si solo se agregó un costo sin otros cambios, mostrar mensaje de éxito
            if (empty($update_fields) && !$has_attachments && !$has_update_reasons) {
                $message = "Costo agregado correctamente";
                $messageType = "success";
            } elseif (empty($message)) {
                $message = "Ticket actualizado correctamente";
                $messageType = "success";
            }
        }
        
        // Mostrar mensaje solo si no hubo ningún cambio (ni campos, ni archivos, ni costos, ni motivos)
        if (empty($update_fields) && !$has_attachments && !$has_costs && !$has_update_reasons) {
            $message = "No se realizaron cambios en el ticket";
            $messageType = "info";
        }
        
        // Procesar motivos de actualización (independientemente de si hubo otros cambios)
        if ($has_update_reasons) {
            $user_name = getUserName();
            $reasons_added = 0;
            
            foreach ($_POST['update_reasons'] as $reason_text) {
                $reason_text = trim($reason_text);
                if (!empty($reason_text)) {
                    $reason_stmt = $conn->prepare("INSERT INTO ticket_update_reasons (ticket_id, reason, created_by, created_at) VALUES (?, ?, ?, NOW())");
                    $reason_stmt->bind_param("iss", $id, $reason_text, $user_name);
                    $reason_stmt->execute();
                    $reason_stmt->close();
                    $reasons_added++;
                }
            }
            
            // Si solo se agregaron motivos sin otros cambios, mostrar mensaje de éxito
            if (empty($update_fields) && !$has_attachments && !$has_costs && $reasons_added > 0) {
                $message = "Motivo(s) de actualización agregado(s) correctamente";
                $messageType = "success";
            } elseif (empty($message) && $reasons_added > 0) {
                $message = "Ticket actualizado correctamente";
                $messageType = "success";
            }
        }
    }
}

// Obtener el ticket con filtro de empresa
$query_ticket = "SELECT t.*, e.nombre as empresa_nombre FROM tickets t LEFT JOIN empresas_usuarios e ON t.id_empresa = e.id_empresa WHERE t.id = ?";
$params_ticket = [$id];
$types_ticket = 'i';
$query_ticket = addEmpresaFilter($query_ticket, 't.id_empresa', $params_ticket, $types_ticket);
$stmt = $conn->prepare($query_ticket);
$stmt->bind_param($types_ticket, ...$params_ticket);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: tickets.php?error=no_access');
    exit;
}

$ticket = $result->fetch_assoc();
$stmt->close();

$statuses->data_seek(0);
$ticket_closed_by_inactivity = ticketIsClosedByInactivity($ticket);
$ticket_display_status = ticketGetDisplayStatusNameFromRow($ticket);

// Obtener motivos de actualización del ticket
$reasons_query = "SELECT * FROM ticket_update_reasons WHERE ticket_id = ? ORDER BY created_at DESC";
$reasons_stmt = $conn->prepare($reasons_query);
$reasons_stmt->bind_param("i", $id);
$reasons_stmt->execute();
$reasons_result = $reasons_stmt->get_result();
$update_reasons = [];
while ($reason = $reasons_result->fetch_assoc()) {
    $update_reasons[] = $reason;
}
$reasons_stmt->close();

// Obtener archivos adjuntos del ticket
$attachments_query = "SELECT * FROM ticket_attachments WHERE ticket_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($attachments_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$attachments_result = $stmt->get_result();
$ticket_attachments = [];
while ($attachment = $attachments_result->fetch_assoc()) {
    $ticket_attachments[] = $attachment;
}
$stmt->close();

error_log("DEBUG - Edit.php - Ticket ID: $id - Total archivos adjuntos: " . count($ticket_attachments));

// Obtener los costos del ticket con conteo de archivos adjuntos
$costs_query = "
    SELECT tc.*, 
           COUNT(tca.id) as attachments_count
    FROM ticket_costs tc
    LEFT JOIN ticket_cost_attachments tca ON tc.id = tca.cost_id
    WHERE tc.ticket_id = ?
    GROUP BY tc.id
    ORDER BY tc.created_at DESC
";
$stmt = $conn->prepare($costs_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$costs_result = $stmt->get_result();
$ticket_costs = [];
while ($cost = $costs_result->fetch_assoc()) {
    $ticket_costs[] = $cost;
}
$stmt->close();
?>
<?php include '../../includes/modern-header.php'; ?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
}
.main-content.main-content-boxed {
    max-width: 1120px;
    /* No usar margin:0 auto — resetea margin-left y choca con el sidebar */
    margin-top: 2rem;
    margin-bottom: 2rem;
    margin-right: auto;
    padding: 2.5rem 2rem;
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px 0 rgba(32,38,84,0.07);
    min-height: 80vh;
}

/* Estilos para modales - evitar superposición con header */
.modal {
    z-index: 1050 !important;
}
.modal-backdrop {
    z-index: 1040 !important;
}

/* Navbar/header global (no aplicar a paneles del formulario) */
.navbar, .modern-header {
    z-index: 1030 !important;
}

/* Mejorar apariencia de modales */
.modal-content {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 1rem 1rem 0 0;
}

.modal-title {
    font-weight: 600;
    color: #2c3e50;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-radius: 0 0 1rem 1rem;
}

/* Estilos para textareas auto-expandibles */
.auto-expand {
    resize: none;
    overflow-y: hidden;
    transition: height 0.2s ease;
    min-height: calc(1.5em * 3 + 0.75rem + 2px); /* Altura mínima para 3 filas */
}

.auto-expand:focus {
    outline: none;
    border-color: #93a4e0;
    box-shadow: 0 0 0 0.2rem rgba(32, 38, 84, 0.12);
}

/* Formulario de edición — paneles en 2 columnas */
.ticket-edit-form-card {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}
.ticket-edit-form-card > .card-body { padding: 0 !important; }
.ticket-edit-panel {
    height: 100%;
    background: #fff;
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(23, 32, 51, 0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.ticket-edit-panel-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1.1rem 1.2rem;
    border-bottom: 1px solid #edf1f6;
    background: linear-gradient(180deg, #fbfcfe 0%, #f7f9fc 100%);
}
.ticket-edit-panel-icon {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #202654;
    color: #fff;
    flex-shrink: 0;
    font-size: 0.95rem;
}
.ticket-edit-panel-icon--priority { background: #3a4478; }
.ticket-edit-panel-icon--details { background: #475569; }
.ticket-edit-panel-header h4 {
    margin: 0;
    border: 0;
    padding: 0;
    font-size: 1rem;
    font-weight: 750;
    color: #202654;
    letter-spacing: -0.01em;
}
.ticket-edit-panel-subtitle {
    margin: 0.2rem 0 0;
    font-size: 0.8rem;
    color: #64748b;
}
.ticket-edit-panel-body {
    padding: 1.15rem 1.2rem 1.25rem;
    flex: 1 1 auto;
}
.ticket-edit-panel-body .mb-3 { margin-bottom: 1rem !important; }
.ticket-edit-panel-body .mb-3:last-child { margin-bottom: 0 !important; }
.ticket-edit-panel .form-label {
    font-size: 0.84rem;
    font-weight: 650;
    color: #334155;
    margin-bottom: 0.4rem;
}
.ticket-edit-panel .form-control,
.ticket-edit-panel .form-select {
    border-radius: 10px;
    border-color: #d7dee8;
    min-height: 2.55rem;
    padding: 0.6rem 0.85rem;
}
.ticket-edit-panel .form-control:focus,
.ticket-edit-panel .form-select:focus {
    border-color: #93a4e0;
    box-shadow: 0 0 0 0.2rem rgba(32, 38, 84, 0.12);
}
.ticket-edit-panel textarea.form-control { min-height: 120px; }
.ticket-edit-check-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
    margin-top: 0.25rem;
}
.ticket-edit-check {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.85rem 0.95rem;
    border: 1px solid #e8edf5;
    border-radius: 12px;
    background: #f8fafc;
}
.ticket-edit-check .form-check {
    margin: 0;
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
}
.ticket-edit-check .form-check-input {
    margin-top: 0.2rem;
    width: 1.1rem;
    height: 1.1rem;
}
.ticket-edit-check .form-check-input:checked {
    background-color: #202654;
    border-color: #202654;
}
.ticket-edit-check .form-check-label {
    font-weight: 650;
    color: #334155;
}
.ticket-edit-check small {
    margin-left: 1.65rem;
    line-height: 1.35;
}
.ticket-edit-form .form-actions {
    margin-top: 1.25rem !important;
    padding: 1rem 0 0;
    border-top: 1px solid #e6ebf2;
}
@media (max-width: 767.98px) {
    .ticket-edit-columns > [class*="col-"] { margin-bottom: 0.85rem; }
    .ticket-edit-check-grid { grid-template-columns: 1fr; }
}

/*  segundos para los selects */
.select-delay-options option {
    visibility: hidden;
}

.select-delay-options.show-options option {
    visibility: visible;
}
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Editar Ticket #<?php echo $ticket['ticket_number']; ?></h1>
                    <p class="text-muted mb-0">Modifica los datos del ticket y guarda los cambios</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="tickets.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>Ver Detalles
                    </a>
                </div>
            </div>
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <!-- Aquí va el formulario de edición, manteniendo la estructura pero aplicando clases modernas a inputs, selects y botones -->
            <div class="card ticket-edit-form-card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" class="ticket-edit-form">
                            <div class="row g-3 ticket-edit-columns">
                                <div class="col-md-6">
                                    <section class="ticket-edit-panel">
                                        <div class="ticket-edit-panel-header">
                                            <span class="ticket-edit-panel-icon" aria-hidden="true"><i class="fas fa-info-circle"></i></span>
                                            <div>
                                                <h4>Información Básica</h4>
                                                <p class="ticket-edit-panel-subtitle">Solicitante, categoría, estado y asignación</p>
                                            </div>
                                        </div>
                                        <div class="ticket-edit-panel-body">
                                    <div class="mb-3">
                                        <label for="requester_id" class="form-label">Solicitante *</label>
                                        <select class="form-select" id="requester_id" name="requester_id" required>
                                            <option value="">Seleccione un solicitante</option>
                                            <?php while ($requester = $requesters->fetch_assoc()): ?>
                                                <option value="<?php echo $requester['id']; ?>" <?php echo ($requester['id'] == $ticket['requester_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($requester['name']); ?>
                                                    <?php if (!empty($requester['company_name'])): ?>
                                                        - <?php echo htmlspecialchars($requester['company_name']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Categoría</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Seleccione una categoría</option>
                                            <?php while ($category = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo $category['id']; ?>" <?php echo ($ticket['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status_id" class="form-label d-flex align-items-center gap-2 flex-wrap">
                                            Estado
                                            <?php if ($ticket_closed_by_inactivity): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-clock me-1"></i>Cerrado por inactividad
                                            </span>
                                            <?php endif; ?>
                                        </label>
                                        <select class="form-select" id="status_id" name="status_id">
                                            <option value="">Seleccione un estado</option>
                                            <?php while ($status = $statuses->fetch_assoc()): ?>
                                                <?php
                                                $option_label = $status['name'];
                                                if ($ticket_closed_by_inactivity && (int) $status['id'] === 3) {
                                                    $option_label = 'Cerrado por inactividad';
                                                }
                                                ?>
                                                <option value="<?php echo $status['id']; ?>" <?php echo ($ticket['status_id'] == $status['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($option_label); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="contact_mode_id" class="form-label">Modo de Contacto</label>
                                        <select class="form-select" id="contact_mode_id" name="contact_mode_id">
                                            <option value="">Seleccione un modo de contacto</option>
                                            <?php while ($contact_mode = $contact_modes->fetch_assoc()): ?>
                                                <option value="<?php echo $contact_mode['id']; ?>" <?php echo ($ticket['contact_mode_id'] == $contact_mode['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($contact_mode['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <!--<?php if (columnExists($conn, 'tickets', 'whatsapp_enabled')): ?>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="whatsapp_enabled" value="0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                id="whatsapp_enabled"
                                                name="whatsapp_enabled"
                                                value="1"
                                                <?php echo !empty($ticket['whatsapp_enabled']) ? 'checked' : ''; ?>
                                            >
                                            <label class="form-check-label" for="whatsapp_enabled">
                                                Habilitar conversación por WhatsApp
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            Si está activo, en la vista del ticket se muestra el chat y se pueden enviar mensajes por WhatsApp.
                                        </div>
                                    </div>
                                    <?php endif; ?> -->
                                    
                                    <div class="mb-3">
                                        <label for="technician_id" class="form-label">Técnico Asignado</label>
                                        <select class="form-select" id="technician_id" name="technician_id">
                                            <option value="">Seleccione un técnico</option>
                                            <?php while ($technician = $technicians->fetch_assoc()): ?>
                                                <option value="<?php echo $technician['id']; ?>" <?php echo ($ticket['technician_id'] == $technician['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($technician['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                        </div>
                                    </section>
                                </div>

                                <div class="col-md-6">
                                    <section class="ticket-edit-panel">
                                        <div class="ticket-edit-panel-header">
                                            <span class="ticket-edit-panel-icon ticket-edit-panel-icon--priority" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                                            <div>
                                                <h4>Clasificación y Prioridad</h4>
                                                <p class="ticket-edit-panel-subtitle">Nivel, impacto y prioridad del ticket</p>
                                            </div>
                                        </div>
                                        <div class="ticket-edit-panel-body">
                                    <div class="mb-3">
                                        <label for="classification_id" class="form-label">Clasificación</label>
                                        <select class="form-select" id="classification_id" name="classification_id">
                                            <option value="">Seleccione una clasificación</option>
                                            <?php while ($classification = $classifications->fetch_assoc()): ?>
                                                <option value="<?php echo $classification['id']; ?>" <?php echo ($ticket['classification_id'] == $classification['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($classification['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="level_id" class="form-label">Nivel</label>
                                        <select class="form-select" id="level_id" name="level_id">
                                            <option value="">Seleccione un nivel</option>
                                            <?php while ($level = $levels->fetch_assoc()): ?>
                                                <option value="<?php echo $level['id']; ?>" <?php echo ($ticket['level_id'] == $level['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($level['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="impact_id" class="form-label">Impacto</label>
                                        <select class="form-select" id="impact_id" name="impact_id">
                                            <option value="">Seleccione un impacto</option>
                                            <?php while (
                                                $impact = $impacts->fetch_assoc()
                                            ): ?>
                                                <option value="<?php echo $impact['id']; ?>" <?php echo ($ticket['impact_id'] == $impact['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($impact['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <!-- <div class="mb-3">
                                        <label for="impact_details" class="form-label">Detalles del Impacto</label>
                                        <input type="text" class="form-control" id="impact_details" name="impact_details" value="<?php echo htmlspecialchars($ticket['impact_details'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="urgency_id" class="form-label">Urgencia</label>
                                        <select class="form-select" id="urgency_id" name="urgency_id">
                                            <option value="">Seleccione una urgencia</option>
                                            <?php while ($urgency = $urgencies->fetch_assoc()): ?>
                                                <option value="<?php echo $urgency['id']; ?>" <?php echo ($ticket['urgency_id'] == $urgency['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($urgency['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div> -->
                                    
                                    <div class="mb-3">
                                        <label for="priority_id" class="form-label">Prioridad</label>
                                        <select class="form-select" id="priority_id" name="priority_id">
                                            <option value="">Seleccione una prioridad</option>
                                            <?php while ($priority = $priorities->fetch_assoc()): ?>
                                                <option value="<?php echo $priority['id']; ?>" <?php echo ($ticket['priority_id'] == $priority['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($priority['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                        </div>
                                    </section>
                                </div>
                            </div>

                            <section class="ticket-edit-panel mt-3">
                                <div class="ticket-edit-panel-header">
                                    <span class="ticket-edit-panel-icon ticket-edit-panel-icon--details" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                                    <div>
                                        <h4>Detalles del Ticket</h4>
                                        <p class="ticket-edit-panel-subtitle">Descripción, resolución, adjuntos y cierre</p>
                                    </div>
                                </div>
                                <div class="ticket-edit-panel-body">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descripción del Problema *</label>
                                        <textarea class="form-control auto-expand" id="description" name="description" rows="5" required><?php echo htmlspecialchars($ticket['description']); ?></textarea>
                                    </div>
                                    
                                    <!-- Sección de Motivos de Actualización -->
                                    <div class="card mb-3">
                                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <h5 class="mb-0">Motivos de Actualización</h5>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="addUpdateReason()">
                                                <i class="fas fa-plus"></i> Agregar Motivo
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="update_reasons_container">
                                                <!-- Los motivos se agregarán dinámicamente aquí -->
                                            </div>
                                            
                                            <!-- Lista de motivos existentes -->
                                            <?php if (!empty($update_reasons)): ?>
                                                <div class="mt-3">
                                                    <h6>Motivos Existentes:</h6>
                                                    <div class="list-group">
                                                        <?php foreach ($update_reasons as $reason): ?>
                                                            <div class="list-group-item">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div class="flex-grow-1">
                                                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($reason['reason'], ENT_QUOTES, 'UTF-8')); ?></p>
                                                                        <small class="text-muted">
                                                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($reason['created_at'])); ?>
                                                                            <?php if (!empty($reason['created_by'])): ?>
                                                                                | <i class="fas fa-user"></i> <?php echo htmlspecialchars($reason['created_by']); ?>
                                                                            <?php endif; ?>
                                                                        </small>
                                                                    </div>
                                                                    <button type="button" class="btn btn-sm btn-danger ms-2" onclick="deleteUpdateReason(<?php echo $reason['id']; ?>)">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="resolution" class="form-label">Resolución</label>
                                        <textarea class="form-control auto-expand" id="resolution" name="resolution" rows="3"><?php echo htmlspecialchars($ticket['resolution']); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="attachments" class="form-label">Agregar Más Archivos Adjuntos</label>
                                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                                        <small class="text-muted">Puedes seleccionar múltiples archivos</small>
                                    </div>

                                    <?php if (!empty($ticket_attachments)): ?>
                                    <div class="mb-3">
                                        <h5>Archivos Adjuntos Actuales</h5>
                                        <div class="list-group">
                                            <?php foreach ($ticket_attachments as $attachment): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-file me-2"></i>
                                                    <a href="../../download_attachment.php?id=<?php echo $attachment['id']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars($attachment['filename'] ?: basename($attachment['file_path'])); ?>
                                                    </a>
                                                    <?php if (!empty($attachment['size'])): ?>
                                                        <small class="text-muted ms-2">(<?php echo round($attachment['size'] / 1024, 2); ?> KB)</small>
                                                    <?php endif; ?>
                                                </div>
                                                <button type="button" class="btn btn-danger btn-sm delete-attachment" 
                                                        data-attachment-id="<?php echo $attachment['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-secondary">
                                        <i class="fas fa-info-circle"></i> No hay archivos adjuntos en este ticket.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="ticket-edit-check-grid">
                                        <div class="ticket-edit-check">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="close_ticket" name="close_ticket" value="1" <?php echo ($ticket['closed_at']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="close_ticket">Marcar como cerrado</label>
                                            </div>
                                        </div>
                                        <div class="ticket-edit-check">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="close_by_inactivity" name="close_by_inactivity" value="1"
                                                    <?php echo $ticket_closed_by_inactivity ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="close_by_inactivity">
                                                    <i class="fas fa-clock text-warning me-1"></i>Cerrado por inactividad
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">Envía un correo específico explicando el cierre por falta de actividad</small>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <div class="card mb-4 ticket-costs-card mt-3">
                                <div class="card-header ticket-costs-header">
                                    <h5 class="mb-0">Costos del Ticket</h5>
                                    <button type="button" class="btn btn-primary btn-sm ticket-costs-add-btn" data-bs-toggle="modal" data-bs-target="#addCostModal">
                                        <i class="fas fa-plus me-1"></i>Agregar Nuevo Costo
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($ticket_costs)): ?>
                                    <div class="ticket-costs-wrap mt-2">
                                        <table class="table ticket-costs-table" id="costsTable">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Descripción</th>
                                                    <th>Monto</th>
                                                    <th>Archivo</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ticket_costs as $cost): ?>
                                                <tr id="cost-row-<?php echo $cost['id']; ?>">
                                                    <td data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($cost['created_at'])); ?></td>
                                                    <td data-label="Descripción"><?php echo htmlspecialchars($cost['description']); ?></td>
                                                    <td data-label="Monto" class="cost-amount-cell"><strong>$<?php echo number_format($cost['amount'], 2); ?></strong></td>
                                                    <td data-label="Archivo" class="cost-files-cell" data-cost-id="<?php echo $cost['id']; ?>">
                                                        <?php if ($cost['attachments_count'] > 0): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="showCostFiles(<?php echo $cost['id']; ?>)">
                                                                <i class="fas fa-paperclip"></i>
                                                                <?php echo $cost['attachments_count']; ?> archivo<?php echo $cost['attachments_count'] > 1 ? 's' : ''; ?>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin archivos</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Acciones" class="ticket-costs-actions">
                                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="editCost(<?php echo $cost['id']; ?>, '<?php echo addslashes(htmlspecialchars($cost['description'])); ?>', <?php echo $cost['amount']; ?>, '')">
                                                            <i class="fas fa-edit"></i><span class="ticket-costs-action-label"> Editar</span>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCost(<?php echo $cost['id']; ?>)">
                                                            <i class="fas fa-trash"></i><span class="ticket-costs-action-label"> Eliminar</span>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-secondary mt-3 mb-0">
                                        <i class="fas fa-info-circle"></i> No hay costos registrados para este ticket. Haga clic en "Agregar Nuevo Costo" para comenzar.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="mt-4 form-actions d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Actualizar Ticket</button>
                                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
        </main>
    </div>
</div>
</div>

<!-- Modal para agregar nuevo costo -->
<div class="modal fade" id="addCostModal" tabindex="-1" aria-labelledby="addCostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCostModalLabel">
                    <i class="fas fa-plus-circle"></i> Agregar Nuevo Costo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCostForm" enctype="multipart/form-data">
                    <input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_cost_description" class="form-label">Descripción del costo *</label>
                                <input type="text" class="form-control" id="add_cost_description" name="description" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_cost_amount" class="form-label">Monto *</label>
                                <input type="number" class="form-control" id="add_cost_amount" name="amount" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="add_cost_files" class="form-label">
                                    <i class="fas fa-paperclip"></i> Archivos adjuntos (opcional)
                                </label>
                                <input type="file" class="form-control" id="add_cost_files" name="cost_files[]" multiple>
                                <small class="text-muted">Puedes seleccionar múltiples archivos para adjuntar evidencia o documentación</small>
                            </div>
                            
                            <!-- Preview de archivos seleccionados -->
                            <div id="add_files_preview" class="mb-3" style="display: none;">
                                <label class="form-label text-success">
                                    <i class="fas fa-check-circle"></i> Archivos seleccionados:
                                </label>
                                <div id="add_files_list" class="list-group">
                                    <!-- Los archivos se mostrarán aquí -->
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveNewCost()">
                    <i class="fas fa-save"></i> Guardar Costo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar costo -->
<div class="modal fade" id="editCostModal" tabindex="-1" aria-labelledby="editCostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCostModalLabel">
                    <i class="fas fa-edit"></i> Editar Costo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editCostForm" enctype="multipart/form-data">
                    <input type="hidden" id="edit_cost_id" name="cost_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_cost_description" class="form-label">Descripción del costo *</label>
                                <input type="text" class="form-control" id="edit_cost_description" name="description" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_cost_amount" class="form-label">Monto *</label>
                                <input type="number" class="form-control" id="edit_cost_amount" name="amount" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <!-- Archivos existentes -->
                            <div id="existing_files_container" class="mb-3" style="display: none;">
                                <label class="form-label">Archivos actuales:</label>
                                <div id="existing_files_list" class="list-group">
                                    <!-- Los archivos se cargarán dinámicamente aquí -->
                                </div>
                            </div>
                            
                            <!-- Subir nuevos archivos -->
                            <div class="mb-3">
                                <label for="edit_cost_files" class="form-label">
                                    <i class="fas fa-paperclip"></i> Agregar nuevos archivos
                                </label>
                                <input type="file" class="form-control" id="edit_cost_files" name="cost_files[]" multiple>
                                <small class="text-muted">Puedes seleccionar múltiples archivos a la vez</small>
                            </div>
                            
                            <!-- Preview de archivos seleccionados -->
                            <div id="new_files_preview" class="mb-3" style="display: none;">
                                <label class="form-label text-success">
                                    <i class="fas fa-check-circle"></i> Archivos seleccionados para subir:
                                </label>
                                <div id="new_files_list" class="list-group">
                                    <!-- Los archivos nuevos se mostrarán aquí -->
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveCostEdit()">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver archivos de un costo -->
<div class="modal fade" id="viewCostFilesModal" tabindex="-1" aria-labelledby="viewCostFilesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCostFilesModalLabel">
                    <i class="fas fa-paperclip"></i> Archivos Adjuntos del Costo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cost_files_loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando archivos...</p>
                </div>
                <div id="cost_files_container" style="display: none;">
                    <div id="cost_files_list" class="list-group">
                        <!-- Los archivos se cargarán dinámicamente aquí -->
                    </div>
                </div>
                <div id="cost_files_empty" style="display: none;" class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay archivos adjuntos en este costo.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-attachment');
    const closeTicketCheckbox = document.getElementById('close_ticket');
    const closeByInactivityCheckbox = document.getElementById('close_by_inactivity');
    const statusSelect = document.getElementById('status_id');
    const technicianSelect = document.getElementById('technician_id');
    
    // Función para manejar el estado de los checkboxes de cierre
    function handleCloseCheckboxes() {
        if (closeByInactivityCheckbox.checked) {
            closeTicketCheckbox.checked = true;
            statusSelect.value = '3';
        }
    }

    if (closeByInactivityCheckbox.checked) {
        handleCloseCheckboxes();
    }
    
    closeTicketCheckbox.addEventListener('change', function() {
        if (this.checked) {
            statusSelect.value = '3'; 
        } else {
            if (statusSelect.value === '3') {
                statusSelect.value = '1';
            }
            // Si se desmarca "cerrar ticket", también desmarcar "cerrar por inactividad"
            closeByInactivityCheckbox.checked = false;
        }
    });
    
    closeByInactivityCheckbox.addEventListener('change', function() {
        handleCloseCheckboxes();
    });

    statusSelect.addEventListener('change', function() {
        if (this.value === '3') {
            closeTicketCheckbox.checked = true;
        } else {
            closeTicketCheckbox.checked = false;
            closeByInactivityCheckbox.checked = false;
        }
    });

    technicianSelect.addEventListener('change', function() {
        if (this.value) {
            statusSelect.value = '2';
        } else {
            statusSelect.value = '1'; 
        }
    });
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (confirm('¿Estás seguro de que deseas eliminar este archivo adjunto?')) {
                const attachmentId = this.dataset.attachmentId;
                
                fetch('delete_attachment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attachment_id: attachmentId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.closest('.list-group-item').remove();
                    } else {
                        alert('Error al eliminar el archivo adjunto');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el archivo adjunto');
                });
            }
        });
    });

    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const closeTicketCheckbox = document.getElementById('close_ticket');
        const closeByInactivityCheckbox = document.getElementById('close_by_inactivity');
        const resolution = document.getElementById('resolution').value.trim();
        
        if (closeTicketCheckbox.checked && resolution === '' && !closeByInactivityCheckbox.checked) {
            e.preventDefault();
            alert('No puedes cerrar el ticket sin una resolución.');
            document.getElementById('resolution').focus();
            return;
        }
        
        // Para cierre por inactividad, la resolución puede estar vacía
        if (closeByInactivityCheckbox.checked && resolution === '') {
            // Agregar una resolución automática para el cierre por inactividad
            document.getElementById('resolution').value = 'Ticket cerrado automáticamente por inactividad.';
        }
    });
    
    // ========== Gestión de modales de costos ==========
    const addCostModal = document.getElementById('addCostModal');
    const editCostModal = document.getElementById('editCostModal');
    
    if (addCostModal) {
        addCostModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('addCostForm').reset();
            document.getElementById('add_files_preview').style.display = 'none';
        });
    }
    
    // Preview de archivos en modal de agregar costo
    const addFileInput = document.getElementById('add_cost_files');
    if (addFileInput) {
        addFileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = document.getElementById('add_files_preview');
            const filesList = document.getElementById('add_files_list');
            
            // Límites de PHP (en bytes)
            const MAX_FILE_SIZE = 40 * 1024 * 1024; // 40MB por archivo
            const MAX_POST_SIZE = 40 * 1024 * 1024; // 40MB total POST
            
            if (files.length > 0) {
                filesList.innerHTML = '';
                let totalSize = 0;
                let hasOversizedFile = false;
                
                Array.from(files).forEach((file, index) => {
                    totalSize += file.size;
                    const isTooBig = file.size > MAX_FILE_SIZE;
                    if (isTooBig) hasOversizedFile = true;
                    
                    const fileItem = document.createElement('div');
                    fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                    
                    if (isTooBig) {
                        fileItem.classList.add('border-danger');
                        fileItem.innerHTML = `
                            <div>
                                <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                                ${file.name}
                                <small class="text-danger ms-2">(${formatFileSize(file.size)} - ¡Demasiado grande!)</small>
                            </div>
                            <span class="badge bg-danger">Excede 40MB</span>
                        `;
                    } else {
                        fileItem.innerHTML = `
                            <div>
                                <i class="fas fa-file-upload me-2 text-success"></i>
                                ${file.name}
                                <small class="text-muted ms-2">(${formatFileSize(file.size)})</small>
                            </div>
                            <span class="badge bg-success">Nuevo</span>
                        `;
                    }
                    filesList.appendChild(fileItem);
                });
                
                // Mostrar advertencia si el tamaño total excede el límite
                if (totalSize > MAX_POST_SIZE || hasOversizedFile) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-danger mt-2 mb-0';
                    warningDiv.innerHTML = `
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>¡Advertencia!</strong><br>
                        ${hasOversizedFile ? '• Uno o más archivos exceden el límite de 40MB por archivo.<br>' : ''}
                        ${totalSize > MAX_POST_SIZE ? '• El tamaño total (' + formatFileSize(totalSize) + ') excede el límite de 40MB.<br>' : ''}
                        <small>Por favor, reduce el tamaño o la cantidad de archivos. 
                        <a href="../../../CONFIGURAR_LIMITES_ARCHIVOS.md" target="_blank" class="text-white text-decoration-underline">
                            <strong>Ver cómo aumentar límites</strong>
                        </a></small>
                    `;
                    filesList.appendChild(warningDiv);
                }
                
                // Mostrar información del tamaño total
                const totalDiv = document.createElement('div');
                totalDiv.className = 'mt-2 p-2 bg-light rounded';
                totalDiv.innerHTML = `
                    <small>
                        <strong>Total:</strong> ${files.length} archivo(s) - ${formatFileSize(totalSize)}
                        ${totalSize > MAX_POST_SIZE ? '<span class="text-danger ms-2"> Excede el límite</span>' : '<span class="text-success ms-2">✓ Dentro del límite</span>'}
                    </small>
                `;
                filesList.appendChild(totalDiv);
                
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
        });
    }
    
    if (editCostModal) {
        editCostModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('editCostForm').reset();
            document.getElementById('existing_files_container').style.display = 'none';
            document.getElementById('new_files_preview').style.display = 'none';
        });
    }
    
    // Mostrar preview de archivos seleccionados en modal de edición
    const fileInput = document.getElementById('edit_cost_files');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = document.getElementById('new_files_preview');
            const filesList = document.getElementById('new_files_list');
            
            if (files.length > 0) {
                filesList.innerHTML = '';
                
                Array.from(files).forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                    fileItem.innerHTML = `
                        <div>
                            <i class="fas fa-file-upload me-2 text-success"></i>
                            ${file.name}
                            <small class="text-muted ms-2">(${formatFileSize(file.size)})</small>
                        </div>
                        <span class="badge bg-success">Nuevo</span>
                    `;
                    filesList.appendChild(fileItem);
                });
                
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
        });
    }
});

// Funciones para gestionar costos
function saveNewCost() {
    const form = document.getElementById('addCostForm');
    const formData = new FormData(form);
    
    // Validar que los campos requeridos estén llenos
    if (!formData.get('description') || !formData.get('amount')) {
        alert('Por favor, completa todos los campos requeridos');
        return;
    }
    
    // Validar tamaño de archivos
    const fileInput = document.getElementById('add_cost_files');
    if (fileInput && fileInput.files.length > 0) {
        const MAX_FILE_SIZE = 40 * 1024 * 1024; // 40MB por archivo
        const MAX_POST_SIZE = 40 * 1024 * 1024; // 40MB total
        let totalSize = 0;
        let hasOversizedFile = false;
        
        Array.from(fileInput.files).forEach(file => {
            totalSize += file.size;
            if (file.size > MAX_FILE_SIZE) {
                hasOversizedFile = true;
            }
        });
        
        if (hasOversizedFile || totalSize > MAX_POST_SIZE) {
            alert(' ERROR: Los archivos seleccionados exceden el límite permitido.\n\n' +
                  '• Tamaño total: ' + formatFileSize(totalSize) + '\n' +
                  '• Límite permitido: 40MB\n\n' +
                  'Por favor, reduce el tamaño o cantidad de archivos, o configura límites mayores en PHP.');
            return;
        }
    }
    
    fetch('add_cost.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar el modal
            var addModal = bootstrap.Modal.getInstance(document.getElementById('addCostModal'));
            addModal.hide();
            
            // Limpiar el formulario
            form.reset();
            
            // Recargar la página para mostrar el nuevo costo
            location.reload();
        } else {
            alert('Error al agregar el costo: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al agregar el costo. Posiblemente los archivos son demasiado grandes.');
    });
}

// Formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Cargar archivos existentes de un costo
function loadCostFiles(costId) {
    fetch('get_cost_files.php?cost_id=' + costId)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.files.length > 0) {
            const container = document.getElementById('existing_files_container');
            const filesList = document.getElementById('existing_files_list');
            
            filesList.innerHTML = '';
            
            data.files.forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                fileItem.innerHTML = `
                    <div>
                        <i class="fas fa-file me-2"></i>
                        <a href="download_cost_file.php?id=${costId}&file_id=${file.id}" target="_blank">
                            ${file.file_name}
                        </a>
                        <small class="text-muted ms-2">(${formatFileSize(file.file_size)})</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCostFile('${file.id}', ${costId})">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                filesList.appendChild(fileItem);
            });
            
            container.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error al cargar archivos:', error);
    });
}

// Eliminar un archivo de un costo
function deleteCostFile(fileId, costId) {
    if (!confirm('¿Está seguro de eliminar este archivo?')) {
        return;
    }
    
    fetch('delete_cost_file.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ file_id: fileId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recargar la lista de archivos
            loadCostFiles(costId);
        } else {
            alert('Error al eliminar el archivo: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar el archivo');
    });
}

// Mostrar modal con los archivos de un costo
function showCostFiles(costId) {
    const modal = new bootstrap.Modal(document.getElementById('viewCostFilesModal'));
    const loadingDiv = document.getElementById('cost_files_loading');
    const containerDiv = document.getElementById('cost_files_container');
    const emptyDiv = document.getElementById('cost_files_empty');
    const filesList = document.getElementById('cost_files_list');
    
    // Mostrar loading
    loadingDiv.style.display = 'block';
    containerDiv.style.display = 'none';
    emptyDiv.style.display = 'none';
    
    // Mostrar el modal
    modal.show();
    
    // Cargar los archivos
    fetch('get_cost_files.php?cost_id=' + costId)
    .then(response => response.json())
    .then(data => {
        loadingDiv.style.display = 'none';
        
        if (data.success && data.files.length > 0) {
            filesList.innerHTML = '';
            
            data.files.forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                fileItem.innerHTML = `
                    <div class="d-flex align-items-center flex-grow-1">
                        <i class="fas fa-file me-3 text-primary" style="font-size: 1.5rem;"></i>
                        <div>
                            <strong>${file.file_name}</strong><br>
                            <small class="text-muted">${formatFileSize(file.file_size)}</small>
                        </div>
                    </div>
                    <div class="btn-group">
                        <a href="download_cost_file.php?id=${costId}&file_id=${file.id}" 
                           class="btn btn-sm btn-outline-primary" 
                           target="_blank"
                           title="Descargar">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                `;
                filesList.appendChild(fileItem);
            });
            
            containerDiv.style.display = 'block';
        } else {
            emptyDiv.style.display = 'block';
        }
    })
    .catch(error => {
        loadingDiv.style.display = 'none';
        emptyDiv.style.display = 'block';
        console.error('Error al cargar archivos:', error);
    });
}

function editCost(costId, description, amount, fileName) {
    // Configurar el modal con los datos del costo
    document.getElementById('edit_cost_id').value = costId;
    document.getElementById('edit_cost_description').value = description;
    document.getElementById('edit_cost_amount').value = amount;
    
    // Limpiar archivos previos
    document.getElementById('existing_files_list').innerHTML = '';
    document.getElementById('existing_files_container').style.display = 'none';
    document.getElementById('new_files_preview').style.display = 'none';
    document.getElementById('edit_cost_files').value = '';
    
    // Cargar archivos existentes del costo
    loadCostFiles(costId);
    
    // Mostrar el modal
    var editModal = new bootstrap.Modal(document.getElementById('editCostModal'));
    editModal.show();
}


function saveCostEdit() {
    const formData = new FormData(document.getElementById('editCostForm'));
    
    fetch('update_cost.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar el modal
            var editModal = bootstrap.Modal.getInstance(document.getElementById('editCostModal'));
            editModal.hide();
            
            // Recargar la página para mostrar los cambios
            location.reload();
        } else {
            alert('Error al actualizar el costo: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar el costo');
    });
}

function deleteCost(costId) {
    if (!confirm('¿Está seguro de que desea eliminar este costo?')) {
        return;
    }
    
    fetch('delete_cost.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ cost_id: costId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Eliminar la fila de la tabla
            const row = document.getElementById('cost-row-' + costId);
            if (row) {
                row.remove();
            }
            
            // Si no quedan más filas, mostrar mensaje
            const tbody = document.querySelector('#costsTable tbody');
            if (tbody && tbody.children.length === 0) {
                location.reload(); // Recargar para mostrar el mensaje de "sin costos"
            }
        } else {
            alert('Error al eliminar el costo: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar el costo');
    });
}

// Funciones para manejar motivos de actualización
let reasonCounter = 0;

function addUpdateReason() {
    reasonCounter++;
    const container = document.getElementById('update_reasons_container');
    const reasonDiv = document.createElement('div');
    reasonDiv.className = 'mb-3 update-reason-item';
    reasonDiv.id = 'reason-' + reasonCounter;
    reasonDiv.innerHTML = `
        <div class="input-group">
            <textarea class="form-control" name="update_reasons[]" rows="2" placeholder="Ingrese el motivo de la actualización..." required></textarea>
            <button type="button" class="btn btn-danger" onclick="removeUpdateReason('reason-${reasonCounter}')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(reasonDiv);
}

function removeUpdateReason(reasonId) {
    const reasonElement = document.getElementById(reasonId);
    if (reasonElement) {
        reasonElement.remove();
    }
}

function deleteUpdateReason(reasonId) {
    if (!confirm('¿Está seguro de que desea eliminar este motivo de actualización?')) {
        return;
    }
    
    fetch('delete_update_reason.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ reason_id: reasonId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error al eliminar el motivo: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar el motivo');
    });
}

// Función para auto-expandir textareas
function autoExpandTextarea(textarea) {
    // Resetear altura para obtener el scrollHeight correcto
    textarea.style.height = 'auto';
    // Ajustar altura al contenido, con un mínimo de rows iniciales
    const minHeight = parseInt(getComputedStyle(textarea).lineHeight) * parseInt(textarea.getAttribute('rows') || 3);
    textarea.style.height = Math.max(minHeight, textarea.scrollHeight) + 'px';
}

// Aplicar auto-expansión a todos los textareas con clase auto-expand
document.addEventListener('DOMContentLoaded', function() {
    const autoExpandTextareas = document.querySelectorAll('.auto-expand');
    
    autoExpandTextareas.forEach(textarea => {
        // Ajustar altura inicial
        autoExpandTextarea(textarea);
        
        // Ajustar altura al escribir
        textarea.addEventListener('input', function() {
            autoExpandTextarea(this);
        });
        
        // Ajustar altura al pegar contenido
        textarea.addEventListener('paste', function() {
            setTimeout(() => autoExpandTextarea(this), 10);
        });
    });
});

//  aparición de las opciones de TODOS los select
document.addEventListener('DOMContentLoaded', function () {
    const selects = document.querySelectorAll('select');
    selects.forEach(function (select) {
        select.classList.add('select-delay-options');
        select.addEventListener('mousedown', function () {

            this.classList.remove('show-options');

            setTimeout(() => {
                this.classList.add('show-options');
            }, 1000);

        });

        select.addEventListener('focus', function () {
            this.classList.remove('show-options');
            setTimeout(() => {
                this.classList.add('show-options');
            }, 1000);
        });
    });
});

</script>