<?php
/**
 * Notificaciones por correo al técnico asignado (tareas y actividades)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../conexion/config_mailer.php';
require_once __DIR__ . '/../conexion/get_empresa_email_config.php';
require_once __DIR__ . '/task_rich_text_helper.php';
require_once __DIR__ . '/task_dates_helper.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

function taskMailLog(string $message, string $level = 'INFO'): void
{
    error_log('[TaskMail][' . $level . '] ' . $message);
}

function taskFetchTechnician(mysqli $conn, int $technician_id): ?array
{
    if ($technician_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, name, email FROM technicians WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $technician_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function taskDescriptionForEmail(?string $html): string
{
    $sanitized = taskSanitizeRichDescription((string) $html);
    if ($sanitized === '' || taskRichDescriptionIsEmpty($sanitized)) {
        return '<p style="margin:0;color:#6c757d;">Sin descripción.</p>';
    }

    return $sanitized;
}

function taskPlainDescriptionForEmail(?string $html): string
{
    $sanitized = taskSanitizeRichDescription((string) $html);
    if ($sanitized === '' || taskRichDescriptionIsEmpty($sanitized)) {
        return 'Sin descripción.';
    }

    $plain = html_entity_decode(strip_tags($sanitized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\s+/u', ' ', trim($plain));

    return $plain !== '' ? $plain : 'Sin descripción.';
}

function taskBuildDetailsBlockHtml(string $subject, ?string $startDate, ?string $endDate, string $descriptionHtml): string
{
    $subjectEsc = htmlspecialchars($subject !== '' ? $subject : 'Sin asunto', ENT_QUOTES, 'UTF-8');
    $startDisplay = htmlspecialchars(taskFormatDateDisplay($startDate), ENT_QUOTES, 'UTF-8');
    $endDisplay = htmlspecialchars(taskFormatDateDisplay($endDate), ENT_QUOTES, 'UTF-8');

    return "
        <h3 style='margin: 0 0 15px 0; color: #2c3e50; font-size: 18px;'>Detalles</h3>
        <p style='margin: 0 0 8px 0; font-size: 13px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;'>Asunto</p>
        <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 18px;'>
            <strong style='color: #2c3e50; font-size: 16px;'>{$subjectEsc}</strong>
        </div>
        <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='margin-bottom: 18px; border-collapse: collapse;'>
            <tr>
                <td width='50%' style='padding-right: 8px; vertical-align: top;'>
                    <p style='margin: 0 0 8px 0; font-size: 13px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;'>Fecha de inicio</p>
                    <div style='background: #fff; padding: 12px 15px; border-radius: 8px; border: 1px solid #dee2e6; color: #2c3e50; font-size: 15px;'>{$startDisplay}</div>
                </td>
                <td width='50%' style='padding-left: 8px; vertical-align: top;'>
                    <p style='margin: 0 0 8px 0; font-size: 13px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;'>Fecha de término</p>
                    <div style='background: #fff; padding: 12px 15px; border-radius: 8px; border: 1px solid #dee2e6; color: #2c3e50; font-size: 15px;'>{$endDisplay}</div>
                </td>
            </tr>
        </table>
        <p style='margin: 0 0 8px 0; font-size: 13px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;'>Descripción</p>
        <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; color: #495057; font-size: 15px; line-height: 1.55;'>
            {$descriptionHtml}
        </div>";
}

function taskBuildAssignmentEmailHtml(
    string $systemTitle,
    string $headerSubtitle,
    string $gradientFrom,
    string $gradientTo,
    string $borderColor,
    string $boxGradientFrom,
    string $boxGradientTo,
    string $technicianName,
    string $introText,
    string $detailsHtml
): string {
    $technicianEsc = htmlspecialchars($technicianName, ENT_QUOTES, 'UTF-8');
    $systemTitleEsc = htmlspecialchars($systemTitle, ENT_QUOTES, 'UTF-8');
    $headerSubtitleEsc = htmlspecialchars($headerSubtitle, ENT_QUOTES, 'UTF-8');

    return "<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
  <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
    <div style='background: linear-gradient(135deg, {$gradientFrom} 0%, {$gradientTo} 100%); padding: 30px; text-align: center; color: white;'>
      <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>{$systemTitleEsc}</h2>
      <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>{$headerSubtitleEsc}</p>
    </div>
    <div style='padding: 40px 30px;'>
      <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>Hola <strong>{$technicianEsc}</strong>,</p>
      <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>{$introText}</p>
      <div style='background: linear-gradient(135deg, {$boxGradientFrom} 0%, {$boxGradientTo} 100%); padding: 25px; border-radius: 12px; border-left: 5px solid {$borderColor}; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
        {$detailsHtml}
      </div>
      <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
        Ingrese al sistema para revisar los detalles y dar seguimiento. Si tiene dudas, responda directamente a este correo.
      </p>
    </div>
    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
      <div style='color: #6c757d; font-size: 12px;'>
        <p style='margin: 0 0 10px 0;'><strong>GK</strong></p>
        <p style='margin: 0; opacity: 0.8;'>
          Este es un mensaje automático del sistema de gestión de tareas.
          Para continuar la conversación, responda directamente a este correo.
        </p>
      </div>
    </div>
  </div>
</body>
</html>";
}

function taskSendTechnicianMail(
    mysqli $conn,
    ?int $id_empresa,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $plainBody,
    string $logContext
): array {
    taskMailLog(
        'Preparando envío (' . $logContext . ') | Empresa: ' . ($id_empresa ?? 'global') .
        ' | Para: ' . $toName . ' <' . $toEmail . '> | Asunto: ' . $subject,
        'INFO'
    );

    $mail = createMailerForEmpresa($id_empresa, $conn);
    if (!$mail instanceof PHPMailer) {
        $msg = 'No se pudo crear el mailer SMTP';
        taskMailLog('Fallo al configurar mailer (' . $logContext . ') | ' . $msg, 'ERROR');
        return ['success' => false, 'message' => $msg];
    }

    $config = getEmpresaEmailConfig($id_empresa, $conn);
    $smtpHost = $config['smtp_host'] ?? '—';
    $smtpPort = $config['smtp_port'] ?? '—';
    $fromEmail = $config['smtp_from_email'] ?? '—';
    taskMailLog(
        'Mailer OK (' . $logContext . ') | SMTP: ' . $smtpHost . ':' . $smtpPort . ' | From: ' . $fromEmail,
        'INFO'
    );

    try {
        $mail->clearAddresses();
        $mail->clearReplyTos();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->addReplyTo('soporte@securiti.info', 'Sistema de Tareas');

        if (!$mail->send()) {
            throw new MailerException($mail->ErrorInfo ?: 'Error desconocido al enviar');
        }

        taskMailLog(
            'Correo enviado correctamente (' . $logContext . ') | Para: ' . $toName . ' <' . $toEmail . '> | Asunto: ' . $subject,
            'OK'
        );

        return ['success' => true, 'message' => 'Correo enviado correctamente'];
    } catch (MailerException $e) {
        $msg = $e->getMessage();
        taskMailLog(
            'Fallo al enviar correo (' . $logContext . ') | Para: ' . $toName . ' <' . $toEmail . '> | Error: ' . $msg,
            'ERROR'
        );
        return ['success' => false, 'message' => $msg];
    }
}

function taskFetchTaskEmailContext(mysqli $conn, int $task_id): ?array
{
    if ($task_id <= 0) {
        return null;
    }

    $hasDates = taskTableHasDateColumns($conn);
    if ($hasDates) {
        $stmt = $conn->prepare(
            'SELECT id, task_number, id_empresa, start_date, end_date FROM tasks WHERE id = ? AND deleted = 0 LIMIT 1'
        );
    } else {
        $stmt = $conn->prepare(
            'SELECT id, task_number, id_empresa FROM tasks WHERE id = ? AND deleted = 0 LIMIT 1'
        );
    }

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    if (!$hasDates) {
        $row['start_date'] = null;
        $row['end_date'] = null;
    }

    return $row;
}

function sendTaskAssignedToTechnicianNotification(
    mysqli $conn,
    ?int $id_empresa,
    int $task_id,
    string $task_number,
    int $technician_id,
    string $subject,
    string $description,
    ?string $start_date = null,
    ?string $end_date = null
): array {
    taskMailLog(
        'Notificación tarea asignada | Tarea ID: ' . $task_id . ' | Número: ' . $task_number .
        ' | Técnico ID: ' . $technician_id,
        'INFO'
    );

    $technician = taskFetchTechnician($conn, $technician_id);
    if (!$technician) {
        $msg = 'Técnico no encontrado';
        taskMailLog('Envío omitido — ' . $msg . ' | Tarea #' . $task_number . ' | Técnico ID: ' . $technician_id, 'WARN');
        return ['success' => false, 'message' => $msg];
    }

    $toEmail = trim((string) ($technician['email'] ?? ''));
    if ($toEmail === '') {
        taskMailLog(
            'Envío omitido — técnico sin correo | Tarea #' . $task_number . ' | Técnico ID: ' . $technician_id,
            'WARN'
        );
        return ['success' => false, 'message' => 'Técnico sin correo electrónico'];
    }

    $technicianName = (string) ($technician['name'] ?? 'Técnico');
    $subjectLine = trim($subject) !== '' ? trim($subject) : 'Sin asunto';
    $descriptionHtml = taskDescriptionForEmail($description);
    $descriptionPlain = taskPlainDescriptionForEmail($description);
    $detailsHtml = taskBuildDetailsBlockHtml($subjectLine, $start_date, $end_date, $descriptionHtml);

    $htmlBody = taskBuildAssignmentEmailHtml(
        'Sistema de Tareas',
        'Tarea #' . $task_number . ' - Asignación',
        '#28a745',
        '#20c997',
        '#28a745',
        '#f8f9fa',
        '#e9ecef',
        $technicianName,
        'Se le ha asignado una <strong>nueva tarea</strong>. A continuación encontrará la información principal:',
        $detailsHtml
    );

    $mailSubject = '[Tarea #' . $task_number . '] Nueva tarea asignada - Sistema de Tareas';
    $plainBody = "Nueva tarea asignada (#{$task_number})\n\n" .
        "Asunto: {$subjectLine}\n" .
        'Fecha de inicio: ' . taskFormatDateDisplay($start_date) . "\n" .
        'Fecha de término: ' . taskFormatDateDisplay($end_date) . "\n\n" .
        "Descripción: {$descriptionPlain}";

    return taskSendTechnicianMail(
        $conn,
        $id_empresa,
        $toEmail,
        $technicianName,
        $mailSubject,
        $htmlBody,
        $plainBody,
        'tarea ID ' . $task_id
    );
}

function sendSubtaskAssignedToTechnicianNotification(
    mysqli $conn,
    ?int $id_empresa,
    int $task_id,
    string $task_number,
    int $subtask_id,
    int $technician_id,
    string $activity_title,
    string $activity_description,
    ?string $start_date = null,
    ?string $end_date = null
): array {
    taskMailLog(
        'Notificación actividad asignada | Tarea ID: ' . $task_id . ' | Número: ' . $task_number .
        ' | Actividad ID: ' . $subtask_id . ' | Técnico ID: ' . $technician_id,
        'INFO'
    );

    $technician = taskFetchTechnician($conn, $technician_id);
    if (!$technician) {
        $msg = 'Técnico no encontrado';
        taskMailLog(
            'Envío omitido — ' . $msg . ' | Actividad ID ' . $subtask_id . ' | Técnico ID: ' . $technician_id,
            'WARN'
        );
        return ['success' => false, 'message' => $msg];
    }

    $toEmail = trim((string) ($technician['email'] ?? ''));
    if ($toEmail === '') {
        taskMailLog(
            'Envío omitido — técnico sin correo | Actividad ID ' . $subtask_id . ' | Técnico ID: ' . $technician_id,
            'WARN'
        );
        return ['success' => false, 'message' => 'Técnico sin correo electrónico'];
    }

    $technicianName = (string) ($technician['name'] ?? 'Técnico');
    $titleLine = trim($activity_title) !== '' ? trim($activity_title) : 'Sin título';
    $descriptionHtml = taskDescriptionForEmail($activity_description);
    $descriptionPlain = taskPlainDescriptionForEmail($activity_description);
    $detailsHtml = taskBuildDetailsBlockHtml($titleLine, $start_date, $end_date, $descriptionHtml);

    $htmlBody = taskBuildAssignmentEmailHtml(
        'Sistema de Tareas',
        'Actividad en tarea #' . $task_number,
        '#007bff',
        '#0056b3',
        '#007bff',
        '#e7f3ff',
        '#cfe2ff',
        $technicianName,
        'Se le ha asignado una <strong>nueva actividad</strong> dentro de la tarea <strong>#' . htmlspecialchars($task_number, ENT_QUOTES, 'UTF-8') . '</strong>. Detalles:',
        $detailsHtml
    );

    $mailSubject = '[Tarea #' . $task_number . '] Nueva actividad asignada - Sistema de Tareas';
    $plainBody = "Nueva actividad asignada en tarea #{$task_number}\n\n" .
        "Asunto: {$titleLine}\n" .
        'Fecha de inicio: ' . taskFormatDateDisplay($start_date) . "\n" .
        'Fecha de término: ' . taskFormatDateDisplay($end_date) . "\n\n" .
        "Descripción: {$descriptionPlain}";

    return taskSendTechnicianMail(
        $conn,
        $id_empresa,
        $toEmail,
        $technicianName,
        $mailSubject,
        $htmlBody,
        $plainBody,
        'actividad ID ' . $subtask_id . ' / tarea ID ' . $task_id
    );
}
