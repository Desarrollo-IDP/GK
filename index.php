<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_errors', 1); 
error_reporting(E_ALL); 

require_once 'Inventario/crud/includes/db.php';
require_once __DIR__ . '/auth.php';
require_once 'Inventario/crud/includes/GoogleAuthenticator.php';

// Incluir PHPMailer para envío de correos
require_once __DIR__ . '/tickets/vendor/autoload.php';
require_once __DIR__ . '/tickets/conexion/config_mailer.php';
require_once __DIR__ . '/includes/password_reset_functions.php';
require_once __DIR__ . '/tickets/conexion/get_empresa_email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Garantizar columnas necesarias en tabla usuarios (evita fallos en login si no se ha abierto gestion-usuarios.php)
try {
    if ($conn instanceof mysqli) {
        $col = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'can_view_inventory_history'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE usuarios ADD COLUMN can_view_inventory_history TINYINT(1) NOT NULL DEFAULT 0 AFTER rol");
        }
        $col = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'can_view_ticket_history'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE usuarios ADD COLUMN can_view_ticket_history TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view_inventory_history");
        }
        $col = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'can_manage_users'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE usuarios ADD COLUMN can_manage_users TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view_ticket_history");
        }
    }
} catch (Exception $e) {
    error_log('[Login] Error asegurando columnas usuarios: ' . $e->getMessage());
}

/**
 * Compara el nombre de usuario con la base de datos (insensible a mayúsculas/minúsculas)
 * @param string $inputUsername El nombre de usuario proporcionado por el usuario
 * @param mysqli $db Conexión a la base de datos
 * @return array|null Datos del usuario si se encuentra, null en caso contrario
 */
function compareUsernameInsensitive($inputUsername, $db) {
    $stmt = $db->prepare('SELECT u.id_usuario, u.nombre_usuario, u.contraseña, u.rol, u.google_auth_secret, u.email, u.can_view_inventory_history, u.can_view_ticket_history, u.can_manage_users, u.id_empresa, e.nombre as empresa_nombre FROM usuarios u LEFT JOIN empresas_usuarios e ON u.id_empresa = e.id_empresa WHERE u.nombre_usuario = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $db->error);
    }

    $stmt->bind_param('s', $inputUsername);
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

$ga = new PHPGangsta_GoogleAuthenticator();

// Procesar recuperación de contraseña (POST normal, redirección al formulario en index)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    $startTime = microtime(true);
    $minResponseTime = 2.5; // Tiempo mínimo de respuesta en segundos (para prevenir timing attacks)
    
    $genericMessage = 'Recibirás un email con instrucciones para restablecer tu contraseña.';
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($username) || empty($email)) {
            throw new Exception('Por favor, completa todos los campos.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Por favor, ingresa un correo electrónico válido.');
        }
        $user = compareUsernameInsensitive($username, $conn);
        if ($user && !empty($user['email'])) {
            $userEmail = strtolower(trim($user['email']));
            $providedEmail = strtolower(trim($email));
            if ($userEmail === $providedEmail) {
                cleanExpiredTokens($conn);
                $token = generateResetToken($user['id_usuario'], $conn);
                $resetUrl = appLocalUrl('/reset-password.php') . '?token=' . rawurlencode($token);
                try {
                    $id_empresa = isset($user['id_empresa']) && !empty($user['id_empresa']) ? (int)$user['id_empresa'] : null;
                    $mail = createMailerForEmpresa($id_empresa, $conn);
                    if (!$mail) {
                        throw new Exception('No hay configuración de correo activa para esta empresa.');
                    }
                    $mail->addAddress($user['email'], $user['nombre_usuario']);
                    $mail->Subject = 'Recuperación de contraseña - SecuriTI';
                    $emailBody = "
                    <!DOCTYPE html>
                    <html lang='es'>
                    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Recuperación de Contraseña</title></head>
                    <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                            <div style='background: linear-gradient(135deg, #1E2A3B 0%, #2c3e50 100%); padding: 30px; text-align: center; color: white;'>
                                <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>SecuriTI</h2>
                                <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Recuperación de Contraseña</p>
                            </div>
                            <div style='padding: 40px 30px;'>
                                <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>Hola <strong>" . htmlspecialchars($user['nombre_usuario']) . "</strong>,</p>
                                <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>Has solicitado recuperar tu contraseña para SecuriTI.</p>
                                <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin: 30px 0;'>
                                    <tr><td align='center' style='padding: 0;'>
                                        <a href='" . htmlspecialchars($resetUrl) . "' style='display: inline-block; padding: 15px 30px; background-color: #1E2A3B; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;'>Restablecer Contraseña</a>
                                    </td></tr>
                                </table>
                                <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #f39c12;'>
                                    <h4 style='margin: 0 0 10px 0; color: #856404; font-size: 16px;'>⚠️ Importante</h4>
                                    <ul style='margin: 10px 0 0 20px; color: #856404; font-size: 14px; padding: 0;'>
                                        <li>Este link expirará en <strong>15 minutos</strong></li>
                                        <li>Si no solicitaste este cambio, ignora este correo</li>
                                        <li>No compartas este link con nadie</li>
                                    </ul>
                                </div>
                            </div>
                            <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                                <p style='margin: 0; color: #6c757d; font-size: 12px;'><strong>SecuriTI</strong> - Mensaje automático.</p>
                            </div>
                        </div>
                    </body>
                    </html>";
                    $mail->Body = $emailBody;
                    $mail->AltBody = "Hola " . $user['nombre_usuario'] . ",\n\nHas solicitado recuperar tu contraseña para SecuriTI.\n\nHaz clic en: " . $resetUrl . "\n\nEste link expirará en 15 minutos.\n\nSaludos,\nEquipo de SecuriTI";
                    if ($mail->send()) {
                        error_log('[Password Reset] Token enviado para usuario: ' . $user['nombre_usuario']);
                        $_SESSION['forgot_message'] = $genericMessage;
                        $_SESSION['forgot_message_type'] = 'success';
                    } else {
                        throw new Exception('Error al enviar el correo: ' . $mail->ErrorInfo);
                    }
                } catch (Exception $e) {
                    error_log('[Password Reset] Error al enviar correo: ' . $e->getMessage());
                    $_SESSION['forgot_message'] = $genericMessage;
                    $_SESSION['forgot_message_type'] = 'success';
                }
            } else {
                $_SESSION['forgot_message'] = $genericMessage;
                $_SESSION['forgot_message_type'] = 'success';
            }
        } else {
            $_SESSION['forgot_message'] = $genericMessage;
            $_SESSION['forgot_message_type'] = 'success';
        }
    } catch (Exception $e) {
        error_log('[Password Reset] Error: ' . $e->getMessage());
        $_SESSION['forgot_message'] = $e->getMessage();
        $_SESSION['forgot_message_type'] = 'error';
    }
    
    // Se omite el retraso artificial para evitar bloqueos innecesarios en la respuesta.
    
    header('Location: index.php?forgot=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'recover_code') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            if (empty($username) || empty($password)) {
                throw new Exception('Usuario y contraseña son obligatorios');
            }
            
            $user = compareUsernameInsensitive($username, $conn);
            //error_log('[Login] Usuario consultado DB: ' . json_encode($user));
            
            if (!$user) {
                throw new Exception('Usuario no encontrado');
            }
            
            if (!password_verify($password, $user['contraseña'])) {
                throw new Exception('Contraseña incorrecta');
            }
            
            // Check if user has an email
            if (empty($user['email'])) {
                throw new Exception('No hay un correo electrónico asociado a esta cuenta');
            }
            
            // Check if user has a Google Auth secret
            if (empty($user['google_auth_secret'])) {
                throw new Exception('No hay un código de autenticación configurado para este usuario');
            }
            
            // Send email with PHPMailer using company's email config
            try {
                $id_empresa = $user['id_empresa'] ?? null;
                $mail = createMailerForEmpresa($id_empresa, $conn);
                
                if (!$mail) {
                    throw new Exception('No se pudo configurar el servidor de correo');
                }
                
                // Configurar destinatario
                $mail->addAddress($user['email'], $user['nombre_usuario']);
                
                // Configurar asunto
                $mail->Subject = 'Recuperación de código de autenticación - SecuriTI';
                
                // Diseño del correo HTML
                $email_body = "
                    <!DOCTYPE html>
                    <html lang='es'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Recuperación de Código</title>
                    </head>
                    <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                            <!-- Header del correo -->
                            <div style='background: linear-gradient(135deg, #1E2A3B 0%, #2c3e50 100%); padding: 30px; text-align: center; color: white;'>
                                <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>SecuriTI</h2>
                                <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Recuperación de Código de Autenticación</p>
                            </div>
                            
                            <!-- Contenido principal -->
                            <div style='padding: 40px 30px;'>
                                <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                                    Hola <strong>" . htmlspecialchars($user['nombre_usuario']) . "</strong>,
                                </p>
                                
                                <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                                    Has solicitado recuperar tu código de autenticación para SecuriTI.
                                </p>
                                
                                <!-- Código secreto -->
                                <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; border-left: 5px solid #1E2A3B; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                                    <h3 style='margin: 0 0 15px 0; color: #2c3e50; font-size: 18px;'>Tu código secreto es:</h3>
                                    <div style='background: #fff; padding: 20px; border-radius: 8px; border: 2px dashed #1E2A3B; text-align: center;'>
                                        <span style='font-size: 24px; font-weight: bold; color: #1E2A3B; letter-spacing: 3px; font-family: monospace;'>" . $user['google_auth_secret'] . "</span>
                                    </div>
                                </div>
                                
                                <!-- Instrucciones -->
                                <div style='background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #2196f3;'>
                                    <h4 style='margin: 0 0 10px 0; color: #1976d2; font-size: 16px;'>
                                        <i style='margin-right: 8px;'>🔐</i> ¿Cómo configurarlo?
                                    </h4>
                                    <ol style='margin: 10px 0 0 20px; color: #424242; font-size: 14px;'>
                                        <li>Abre Google Authenticator en tu dispositivo móvil</li>
                                        <li>Toca el botón \"+\" para agregar una nueva cuenta</li>
                                        <li>Selecciona \"Introducir una clave de configuración\"</li>
                                        <li>Nombre de cuenta: <strong>SecuriTI - " . htmlspecialchars($user['nombre_usuario']) . "</strong></li>
                                        
                                    </ol>
                                </div>
                                
                                <p style='color: #6c757d; font-size: 14px; margin-top: 30px;'>
                                    <strong>⚠️ Importante:</strong> Por seguridad, mantén este código en un lugar seguro.
                                </p>
                            </div>
                            
                            <!-- Footer -->
                            <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                                <div style='color: #6c757d; font-size: 12px;'>
                                    <p style='margin: 0 0 10px 0;'>
                                        <strong>SecuriTI</strong> 
                                    </p>
                                    <p style='margin: 0; opacity: 0.8;'>
                                        Este es un mensaje automático, por favor no responder.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>";
                
                $mail->Body = $email_body;
                $mail->AltBody = "Hola " . $user['nombre_usuario'] . ",\n\n" .
                                "Has solicitado recuperar tu código de autenticación para SecuriTI.\n\n" .
                                "Tu código secreto es: " . $user['google_auth_secret'] . "\n\n" .
                                "Por favor, configura este código en tu aplicación Google Authenticator.\n\n" .
                                "Este correo es automático, por favor no respondas.\n\n" .
                                "Saludos,\nEquipo de SecuriTI";
                
                // Enviar el correo
                if ($mail->send()) {
                    error_log("Recuperación de código solicitada para el usuario: " . $user['nombre_usuario']);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Código enviado exitosamente a tu correo electrónico.'
                    ]);
                } else {
                    throw new Exception('Error al enviar el correo: ' . $mail->ErrorInfo);
                }
            } catch (Exception $e) {
                // Si falla el envío, mostrar el código directamente
                error_log("Error al enviar correo de recuperación: " . $e->getMessage());
                echo json_encode([
                    'success' => true,
                    'message' => 'No se pudo enviar el correo. Tu código secreto es: ' . $user['google_auth_secret']
                ]);
            }
            
            exit;
        } else {
            // Existing login code
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $code = trim($_POST['code'] ?? '');

            //error_log("Datos recibidos: username=$username, password=$password, code=$code"); 

            if (empty($username) || empty($password)) {
                throw new Exception('Usuario y contraseña son obligatorios');
            }

            $user = compareUsernameInsensitive($username, $conn);
            
            if (!$user) {
                throw new Exception('Usuario no encontrado');
            }

            //error_log("Usuario encontrado: " . print_r($user, true)); 

            if (!password_verify($password, $user['contraseña'])) {
                throw new Exception('Contraseña incorrecta');
            }

            if (empty($code)) {
                $secret = $user['google_auth_secret'];

                if (empty($secret)) {
                    $secret = $ga->createSecret();
                    $query = "UPDATE usuarios SET google_auth_secret = ? WHERE id_usuario = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Error al preparar la consulta: ' . $conn->error);
                    }
                    $stmt->bind_param('si', $secret, $user['id_usuario']);
                    if (!$stmt->execute()) {
                        throw new Exception('Error al guardar el secreto: ' . $stmt->error);
                    }
                    
                    $qrCodeUrl = $ga->getQRCodeGoogleUrl('SecuriTI - ' . $username, $secret);
                    echo json_encode(['showQR' => true, 'qrCodeUrl' => $qrCodeUrl]);
                    exit;
                } else{
                    
                    echo json_encode(['showCodeInput' => true]);
                    exit;
                }
            }

            if (!$ga->verifyCode($user['google_auth_secret'], $code, 2)) { 
                throw new Exception('Código de Google Authenticator incorrecto');
            }

          
            $_SESSION['user'] = [
                'id' => $user['id_usuario'],
                'username' => $user['nombre_usuario'],
                'rol' => $user['rol'],
                'can_view_inventory_history' => isset($user['can_view_inventory_history']) ? (int)$user['can_view_inventory_history'] : 0,
                'can_view_ticket_history' => isset($user['can_view_ticket_history']) ? (int)$user['can_view_ticket_history'] : 0,
                'can_manage_users' => isset($user['can_manage_users']) ? (int)$user['can_manage_users'] : 0,
                'id_empresa' => isset($user['id_empresa']) ? (int)$user['id_empresa'] : null,
                'empresa_nombre' => isset($user['empresa_nombre']) ? $user['empresa_nombre'] : null,
            ];
            //error_log('[Login] Sesion user seteada: ' . json_encode($_SESSION['user']));
            echo json_encode(['success' => true, 'redirect' => 'direc.php']);
        }
    } catch (Exception $e) {
        //error_log("Error en el inicio de sesión: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GK Corporativo - Iniciar Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary-color: #0b1a2e;
            --secondary-color: #162438;
            --accent-color: #2563eb;
            --black: #000000;
            --white: #ffffff;
            --gray: #f8fafc;
            --gray-border: #e2e8f0;
            --gray-2: #64748b;

            --facebook-color: #4267B2;
            --google-color: #DB4437;
            --twitter-color: #1DA1F2;
            --insta-color: #E1306C;
        }

        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100vh;
            overflow: hidden;
            background-color: #ffffff;
        }

        .container {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            height: 100vh;
        }

        .col {
            width: 50%;
        }

        .align-items-center {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .flex-col {
            flex-direction: column;
        }

        .form-wrapper {
            width: 100%;
            max-width: 27rem;
            padding: 1.5rem;
        }

        .form {
            padding: 2.25rem 2rem;
            background-color: var(--white);
            border-radius: 1.5rem;
            width: 100%;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(226, 232, 240, 0.85);
            transform: scale(0);
            transition: .5s ease-in-out;
            transition-delay: 1s;
        }

        .form h2 {
            font-size: 1.65rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1.25rem;
            letter-spacing: -0.5px;
        }

        .input-group {
            position: relative;
            width: 100%;
            margin: 1.1rem 0;
        }

        .input-group i {
            position: absolute;
            top: 50%;
            left: 1.15rem;
            transform: translateY(-50%);
            font-size: 1.35rem;
            color: #94a3b8;
            pointer-events: none;
            transition: color 0.25s ease;
            z-index: 2;
        }

        .input-group input {
            width: 100%;
            padding: 0.95rem 1.15rem 0.95rem 3.15rem;
            font-size: 0.95rem;
            color: #1e293b;
            background-color: #f1f5f9;
            border-radius: 0.75rem;
            border: 1.5px solid transparent;
            outline: none;
            transition: all 0.25s ease;
        }

        .input-group input:focus {
            background-color: #ffffff;
            border-color: #0b1a2e;
            box-shadow: 0 0 0 4px rgba(11, 26, 46, 0.08);
        }

        .input-group:focus-within i {
            color: #0b1a2e;
        }

        .form button {
            cursor: pointer;
            width: 100%;
            border: none;
            padding: 0.85rem 0;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #162438 0%, #0b1a2e 100%);
            color: var(--white);
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            outline: none;
            box-shadow: 0 4px 14px rgba(11, 26, 46, 0.25);
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }

        .form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(11, 26, 46, 0.35);
            background: linear-gradient(135deg, #1e314b 0%, #0e1e35 100%);
        }

        .form button:active {
            transform: translateY(0);
        }

        .form p {
            margin: 1.15rem 0 0;
            font-size: .88rem;
        }

        .pointer {
            cursor: pointer;
        }

        .message {
            padding: 15px;
            border-radius: .5rem;
            margin-bottom: 1rem;
            font-size: 14px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .container.sign-in .form.sign-in,
        .container.sign-up .form.sign-up {
            transform: scale(1);
        }

        .content-row {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 7;
            width: 100%;
            height: 100vh;
        }

        .col-brand-left {
            padding-right: 3rem;
        }

        .col-brand-right {
            padding-left: 3rem;
        }

        .text {
            margin: 0;
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            z-index: 8;
        }

        .brand-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1rem;
        }

        .brand-logo-card {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45);
            margin-bottom: 1.35rem;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .brand-logo-card:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 22px 42px rgba(0, 0, 0, 0.55);
        }

        .brand-logo-img {
            width: 100%;
            max-width: 210px;
            height: auto;
            aspect-ratio: 1247 / 1155;
            border-radius: 14px;
            display: block;
            object-fit: contain;
        }

        .brand-caption {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-name {
            font-family: 'Cinzel', 'Poppins', serif;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            color: #ffffff;
            text-transform: uppercase;
            margin: 0;
            line-height: 1.25;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5), 0 0 20px rgba(255, 255, 255, 0.2);
            white-space: nowrap;
        }

        .container.sign-in .text.sign-up,
        .container.sign-up .text.sign-in {
            display: none !important;
            opacity: 0;
            visibility: hidden;
        }

        .container.sign-in .text.sign-in,
        .container.sign-up .text.sign-up {
            display: flex !important;
            opacity: 1;
            visibility: visible;
        }

        .container::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            height: 100vh;
            width: 300vw;
            transform: translate(35%, 0);
            background: linear-gradient(135deg, #071322 0%, #0b1a2e 50%, #162438 100%);
            transition: 1s ease-in-out;
            z-index: 6;
            box-shadow: rgba(0, 0, 0, 0.4) 0px 5px 25px;
            border-bottom-right-radius: max(50vw, 50vh);
            border-top-left-radius: max(50vw, 50vh);
        }

        .container.sign-in::before {
            transform: translate(0, 0);
            right: 50%;
        }

        .container.sign-up::before {
            transform: translate(100%, 0);
            right: 50%;
        }

        /* Responsive para Laptops y Tablets Horizontales (769px a 1100px) */
        @media only screen and (max-width: 1100px) and (min-width: 769px) {
            .col-brand-left {
                padding-right: 2rem;
            }
            .brand-logo-img {
                max-width: 175px;
            }
            .brand-name {
                font-size: 1.15rem;
                letter-spacing: 2px;
            }
            .form-wrapper {
                max-width: 23rem;
            }
        }

        /* Responsive para Móviles y Tablets Verticales (<= 768px) */
        @media only screen and (max-width: 768px) {
            html, body {
                height: auto;
                min-height: 100vh;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .container {
                min-height: 100vh;
                height: auto;
                display: flex;
                flex-direction: column;
                background: linear-gradient(180deg, #071322 0%, #0b1a2e 45%, #162438 100%);
            }

            .container::before {
                display: none !important;
            }

            .row {
                height: auto;
                min-height: 100vh;
                flex-direction: column;
                justify-content: flex-start;
                align-items: center;
                width: 100%;
            }

            .content-row {
                position: relative;
                height: auto;
                width: 100%;
                z-index: 5;
                padding-top: 2.5rem;
                padding-bottom: 1.5rem;
            }

            .content-row .col,
            .col-brand-left,
            .col-brand-right {
                width: 100%;
                padding: 0;
            }

            .col.sign-in,
            .col.sign-up {
                width: 100%;
                position: relative;
                transform: none !important;
                padding: 0 1.25rem 2.5rem;
                display: flex;
                justify-content: center;
                z-index: 10;
            }

            .container.sign-in .col.sign-up,
            .container.sign-up .col.sign-in {
                display: none !important;
            }

            .form-wrapper {
                max-width: 26rem;
                width: 100%;
                padding: 0;
            }

            .form {
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 1.5rem;
                padding: 2.25rem 1.75rem;
                background: #ffffff;
                transform: scale(1) !important;
            }

            .brand-wrapper {
                padding: 0.5rem 1rem;
            }

            .brand-logo-card {
                padding: 6px;
                border-radius: 14px;
                margin-bottom: 0.85rem;
                box-shadow: 0 10px 24px rgba(0, 0, 0, 0.4);
            }

            .brand-logo-img {
                max-width: 130px;
                border-radius: 10px;
            }

            .brand-name {
                font-size: 1.15rem;
                letter-spacing: 2px;
                white-space: normal;
            }
        }

        /* Pantallas muy pequeñas (<= 360px) */
        @media only screen and (max-width: 360px) {
            .brand-logo-img {
                max-width: 100px;
            }
            .brand-name {
                font-size: 0.95rem;
                letter-spacing: 1.5px;
            }
            .form {
                padding: 1.75rem 1.25rem;
            }
        }
    </style>
</head>

<body>
    <div id="container" class="container sign-in">
        <div class="row">
            <div class="col align-items-center flex-col sign-up">
                <div class="form-wrapper align-items-center">
                    <div class="form sign-up">
                        <?php
                        $forgotMessage = $_SESSION['forgot_message'] ?? '';
                        $forgotMessageType = $_SESSION['forgot_message_type'] ?? '';
                        if ($forgotMessage !== '') {
                            unset($_SESSION['forgot_message'], $_SESSION['forgot_message_type']);
                        }
                        ?>
                        <?php if ($forgotMessage !== ''): ?>
                            <div class="message <?php echo htmlspecialchars($forgotMessageType); ?>">
                                <?php echo htmlspecialchars($forgotMessage); ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" id="forgotForm">
                            <h2>Recuperar Contraseña</h2>
                            <p>Ingresa tu nombre de usuario y correo electrónico para recibir instrucciones para restablecer tu contraseña.</p>
                            <input type="hidden" name="action" value="request_reset">
                            <div class="input-group">
                                <i class='bx bxs-user'></i>
                                <input type="text" name="username" placeholder="Nombre de usuario" required autocomplete="username">
                            </div>
                            <div class="input-group">
                                <i class='bx bx-mail-send'></i>
                                <input type="email" name="email" placeholder="Correo electrónico" required autocomplete="email">
                            </div>
                            <button type="submit">
                                Enviar instrucciones
                            </button>
                            <p>
                                <span>¿Recordaste tu contraseña?</span>
                                <b onclick="toggle()" class="pointer">Inicia sesión aquí</b>
                            </p>
                        </form>
                    </div>
                </div>

            </div>

            <div class="col align-items-center flex-col sign-in">
                <div class="form-wrapper align-items-center">
                    <div class="form sign-in">
                        <form id="loginForm" method="POST">
                            <input type="hidden" name="action" value="login">
                            <h2>Iniciar Sesión</h2>
                            <div class="input-group">
                                <i class='bx bxs-user'></i>
                                <input type="text" name="username" placeholder="Usuario" required autocomplete="username">
                            </div>
                            <div class="input-group">
                                <i class='bx bxs-lock-alt'></i>
                                <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
                            </div>
                            <button type="submit" id="loginButton">
                                Entrar
                            </button>
                            <p style="margin: 1rem 0; font-size: .9rem; text-align: center;">
                                <a href="#" onclick="toggle(); return false;" style="color: var(--primary-color); text-decoration: none;">
                                    ¿Olvidaste tu contraseña?
                                </a>
                            </p>
                            <div id="qrCodeContainer" style="display: none;">
                                <p id="qrMessage">Escanea el siguiente código QR con Google Authenticator:</p>
                                <img id="qrCodeImage" src="" alt="QR Code">
                                <div class="input-group">
                                    <i class='bx bxs-lock-alt'></i>
                                    <input type="text" name="code" placeholder="Código Google Authenticator">
                                    <a href="#" id="recoverCodeLink" style="color: var(--primary-color); text-decoration: none;">recupera codigo</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row content-row">
            <div class="col align-items-center flex-col col-brand-left">
                <div class="text sign-in">
                    <div class="brand-wrapper">
                        <div class="brand-logo-card">
                            <img src="Inventario/img/gk_emblem.png" alt="GK Corporativo" class="brand-logo-img">
                        </div>
                        <div class="brand-caption">
                            <h1 class="brand-name">GK CORPORATIVO, S.C.</h1>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col align-items-center flex-col col-brand-right">
                <div class="text sign-up">
                    <div class="brand-wrapper">
                        <div class="brand-logo-card">
                            <img src="Inventario/img/gk_emblem.png" alt="GK Corporativo" class="brand-logo-img">
                        </div>
                        <div class="brand-caption">
                            <h1 class="brand-name">GK CORPORATIVO, S.C.</h1>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let container = document.getElementById('container')

        toggle = () => {
            container.classList.toggle('sign-in')
            container.classList.toggle('sign-up')
        }

        setTimeout(() => {
            if (window.location.search.indexOf('forgot=1') !== -1) {
                container.classList.add('sign-up')
                container.classList.remove('sign-in')
            } else {
                container.classList.add('sign-in')
            }
        }, 200)
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            //console.log("Formulario enviado"); 
        
            const formData = new FormData(e.target);
            //console.log("Datos del formulario:", Object.fromEntries(formData)); 
        
            try {
                console.log("Enviando datos al servidor..."); 
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });
        
                console.log("Respuesta recibida:", response); 
        
                if (!response.ok) {
                    throw new Error(`Error HTTP! Estado: ${response.status}`);
                }
        
                const result = await response.json();
                console.log("Respuesta del servidor:", result); 
        
                if (result.error) {
                    alert(result.error);
                } else if (result.showQR) {
                    document.getElementById('qrCodeImage').src = result.qrCodeUrl;
                    document.getElementById('qrCodeContainer').style.display = 'block';
                    document.getElementById('loginButton').textContent = 'Verificar Código';
                    document.querySelector('input[name="code"]').setAttribute('required', true);

                } else if (result.showCodeInput) {
                    document.getElementById('qrCodeContainer').style.display = 'block';
                    document.getElementById('qrCodeImage').style.display = 'none';
                    document.getElementById('loginButton').textContent = 'Verificar Código';
                    document.querySelector('input[name="code"]').setAttribute('required', true);
                } else if (result.success) {
                    window.location.replace(result.redirect);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error en el inicio de sesión. Por favor, intente nuevamente.');
            }
        });
        
        // Función para mostrar notificaciones en el formulario de login
        function showLoginNotification(message, type = 'success', duration = 3000) {
            // Remover notificación existente si hay
            const existingNotification = document.getElementById('loginNotification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.id = 'loginNotification';
            notification.className = 'message ' + type;
            notification.style.cssText = 'transition: opacity 0.5s ease; margin-top: 1rem;';
            notification.textContent = message;
            
            // Insertar al inicio del formulario (después del h2)
            const loginForm = document.getElementById('loginForm');
            const h2Element = loginForm.querySelector('h2');
            if (h2Element) {
                h2Element.insertAdjacentElement('afterend', notification);
            } else {
                loginForm.insertBefore(notification, loginForm.firstChild);
            }
            
            // Auto-desvanecer después del tiempo especificado
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, duration);
        }
        
        // Add event listener for the recover code link
        document.getElementById('recoverCodeLink').addEventListener('click', async (e) => {
            e.preventDefault();
            console.log("Recuperación de código solicitada");
            
            const username = document.querySelector('#loginForm input[name="username"]').value;
            const password = document.querySelector('#loginForm input[name="password"]').value;
            
            if (!username || !password) {
                showLoginNotification('Por favor, ingrese su nombre de usuario y contraseña para recuperar el código.', 'error', 3000);
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'recover_code');
                formData.append('username', username);
                formData.append('password', password);
                
                console.log("Enviando solicitud de recuperación...");
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Error HTTP! Estado: ${response.status}`);
                }
                
                const result = await response.json();
                console.log("Respuesta de recuperación:", result);
                
                if (result.success) {
                    const msg = result.message || 'Se ha enviado el código de recuperación a su correo electrónico.';
                    showLoginNotification(msg, 'success', 3000);
                } else if (result.error) {
                    showLoginNotification(result.error, 'error', 3000);
                }
            } catch (error) {
                console.error('Error en la recuperación:', error);
                showLoginNotification('Error al recuperar el código. Por favor, intente nuevamente.', 'error', 3000);
            }
        });
    </script>
</body>

</html>