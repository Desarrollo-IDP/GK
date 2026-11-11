<?php
// Cargar autoloader antes de usar las clases
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Devuelve un remitente SMTP válido alineado con la cuenta autenticada.
 */
function resolveSmtpFromEmail(array $config): string
{
    $username = trim((string)($config['smtp_username'] ?? ''));
    $from = trim((string)($config['smtp_from_email'] ?? ''));

    if ($username === '') {
        return $from;
    }

    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $username;
    }

    $blockedDomains = ['example.com', 'example.org', 'example.net', 'test.com', 'localhost', 'invalid'];
    $fromDomain = strtolower((string)substr(strrchr($from, '@'), 1));
    if (in_array($fromDomain, $blockedDomains, true)) {
        error_log("SMTP remitente inválido ({$from}); se usará la cuenta autenticada: {$username}");
        return $username;
    }

    $userDomain = strtolower((string)substr(strrchr($username, '@'), 1));
    if ($fromDomain !== $userDomain) {
        error_log("SMTP remitente ({$from}) no coincide con el dominio autenticado ({$userDomain}); se usará: {$username}");
        return $username;
    }

    return $from;
}

/**
 * Normaliza la configuración de correo antes de usarla en envíos.
 */
function normalizeEmpresaEmailConfig(array $config): array
{
    $config['smtp_from_email'] = resolveSmtpFromEmail($config);
    return $config;
}

/**
 * Construye una dirección noreply@ del mismo dominio que la cuenta SMTP.
 */
function resolveNoreplyEmail(array $config): string
{
    $base = resolveSmtpFromEmail($config);
    if ($base === '' || !filter_var($base, FILTER_VALIDATE_EMAIL)) {
        return 'noreply@localhost';
    }

    $domain = strtolower((string)substr(strrchr($base, '@'), 1));
    return 'noreply@' . $domain;
}

/**
 * Configura PHPMailer para notificaciones que no deben recibir respuesta.
 */
function applyNoreplyMailHeaders(PHPMailer $mail, array $config, string $fromName = 'Sistema de Tickets'): void
{
    $noreply = resolveNoreplyEmail($config);
    $name = trim($fromName) !== '' ? $fromName : ($config['smtp_from_name'] ?? 'Sistema de Tickets');

    $mail->setFrom($noreply, $name . ' (No responder)');
    if (!empty($config['smtp_username'])) {
        $mail->Sender = $config['smtp_username'];
    }
    $mail->clearReplyTos();
    $mail->addReplyTo($noreply, 'No responder');
}

/**
 * Obtiene una fila de empresa_email_config desde la base de datos.
 */
function fetchEmpresaEmailConfigRow(mysqli $conn, int $id_empresa): ?array
{
    $stmt = $conn->prepare('SELECT * FROM empresa_email_config WHERE id_empresa = ? AND activo = 1 LIMIT 1');
    if (!$stmt) {
        error_log('Error preparando consulta de email config: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $id_empresa);
    if (!$stmt->execute()) {
        error_log('Error ejecutando consulta de email config: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $config = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $config ?: null;
}

/**
 * Obtiene la configuración de email para una empresa desde empresa_email_config (BD).
 *
 * @param int|null $id_empresa ID de la empresa. Si es null, usa la primera config activa.
 * @param mysqli   $conn       Conexión a la base de datos
 * @return array|null Configuración de email o null si no existe
 */
function getEmpresaEmailConfig($id_empresa, $conn)
{
    if ($id_empresa !== null && (int)$id_empresa > 0) {
        $config = fetchEmpresaEmailConfigRow($conn, (int)$id_empresa);
        return $config ? normalizeEmpresaEmailConfig($config) : null;
    }

    $result = $conn->query('SELECT * FROM empresa_email_config WHERE activo = 1 ORDER BY id_empresa ASC LIMIT 1');
    if ($result && ($config = $result->fetch_assoc())) {
        return normalizeEmpresaEmailConfig($config);
    }

    error_log('No hay configuración de correo activa en empresa_email_config');
    return null;
}

/**
 * Crea PHPMailer o lanza excepción si no hay configuración activa.
 */
function createMailerForEmpresaOrFail($id_empresa, mysqli $conn): PHPMailer
{
    $mail = createMailerForEmpresa($id_empresa, $conn);
    if (!$mail) {
        throw new Exception(
            'No hay configuración de correo activa para esta empresa. Configúrela en Configuración → Email por empresa.'
        );
    }

    return $mail;
}

/**
 * Crea una instancia de PHPMailer configurada para una empresa específica
 * @param int|null $id_empresa ID de la empresa
 * @param mysqli   $conn       Conexión a la base de datos
 * @return PHPMailer|null Instancia de PHPMailer o null en caso de error
 */
function createMailerForEmpresa($id_empresa, $conn)
{
    $config = getEmpresaEmailConfig($id_empresa, $conn);
    if (!$config) {
        error_log('No se pudo obtener configuración de email para empresa: ' . ($id_empresa ?? 'default'));
        return null;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->Port = (int)$config['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);

        $mail->SMTPDebug = (defined('EMAIL_PROCESSOR_DEBUG') && EMAIL_PROCESSOR_DEBUG)
            ? SMTP::DEBUG_SERVER
            : SMTP::DEBUG_OFF;
        $mail->Debugoutput = function ($str, $level) {
            if (function_exists('emailProcessLog')) {
                emailProcessLog('PHPMailer Debug: ' . $str, 'debug');
            }
        };
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->Timeout = 60;

        $fromEmail = $config['smtp_from_email'];
        $fromName = $config['smtp_from_name'] ?? 'Sistema de Tickets';
        $mail->setFrom($fromEmail, $fromName);
        if (!empty($config['smtp_username'])) {
            $mail->Sender = $config['smtp_username'];
        }

        return $mail;
    } catch (Exception $e) {
        error_log('Error al configurar PHPMailer para empresa ' . ($id_empresa ?? 'default') . ': ' . $e->getMessage());
        return null;
    }
}

/**
 * Obtiene la configuración IMAP para una empresa específica
 * @param int|null $id_empresa ID de la empresa
 * @param mysqli   $conn       Conexión a la base de datos
 * @return array|null Configuración IMAP o null si no hay config en BD
 */
function getImapConfigForEmpresa($id_empresa, $conn)
{
    $config = getEmpresaEmailConfig($id_empresa, $conn);
    if (!$config) {
        return null;
    }

    return [
        'host' => $config['imap_host'],
        'port' => $config['imap_port'],
        'username' => $config['imap_username'],
        'password' => $config['imap_password'],
        'folder' => $config['imap_folder'],
        'encryption' => $config['imap_encryption'],
        'options' => $config['imap_options'],
    ];
}
