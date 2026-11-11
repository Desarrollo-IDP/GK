<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'Inventario/crud/includes/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/password_reset_functions.php';
require_once __DIR__ . '/tickets/vendor/autoload.php';
require_once __DIR__ . '/tickets/conexion/get_empresa_email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Función para buscar usuario (incluye id_empresa)
function compareUsernameInsensitive($inputUsername, $db) {
    $stmt = $db->prepare('SELECT u.id_usuario, u.nombre_usuario, u.email, u.id_empresa FROM usuarios u WHERE u.nombre_usuario = ? LIMIT 1');
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

$message = '';
$messageType = ''; // 'success' o 'error'

// Procesar solicitud de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validar campos
        if (empty($username) || empty($email)) {
            throw new Exception('Por favor, completa todos los campos.');
        }
        
        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Por favor, ingresa un correo electrónico válido.');
        }
        
        // Buscar usuario
        $user = compareUsernameInsensitive($username, $conn);
        
        // Mensaje genérico siempre (no revelar si existe o no)
        $genericMessage = 'Recibirás un email con instrucciones para restablecer tu contraseña.';
        
        // Si el usuario existe, tiene email y el email coincide
        if ($user && !empty($user['email'])) {
            $userEmail = strtolower(trim($user['email']));
            $providedEmail = strtolower(trim($email));
            
            if ($userEmail === $providedEmail) {
                // Limpiar tokens expirados
                cleanExpiredTokens($conn);
                
                // Generar token
                $token = generateResetToken($user['id_usuario'], $conn);
                
                // URL base del sitio (configuración fija)
                $resetUrl = appLocalUrl('/reset-password.php') . '?token=' . rawurlencode($token);
                
                // Enviar correo usando configuración de la empresa del usuario
                try {
                    // Obtener id_empresa del usuario (puede ser null)
                    $id_empresa = isset($user['id_empresa']) && !empty($user['id_empresa']) ? (int)$user['id_empresa'] : null;
                    
                    // Crear instancia de PHPMailer con configuración de la empresa
                    $mail = createMailerForEmpresa($id_empresa, $conn);
                    if (!$mail) {
                        throw new Exception('No hay configuración de correo activa para esta empresa.');
                    }

                    $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
                    if ($empresa_config) {
                        error_log('[Password Reset] Usando configuración de empresa ID: ' . ($id_empresa ?? 'default') . ' - SMTP: ' . $empresa_config['smtp_host'] . ' - From: ' . $empresa_config['smtp_from_email']);
                    }
                    
                    $mail->addAddress($user['email'], $user['nombre_usuario']);
                    $mail->Subject = 'Recuperación de contraseña - SecuriTI';
                    
                    // Cuerpo HTML del correo
                    $emailBody = "
                    <!DOCTYPE html>
                    <html lang='es'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Recuperación de Contraseña</title>
                    </head>
                    <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                            <!-- Header del correo -->
                            <div style='background: linear-gradient(135deg, #1E2A3B 0%, #2c3e50 100%); padding: 30px; text-align: center; color: white;'>
                                <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>SecuriTI</h2>
                                <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Recuperación de Contraseña</p>
                            </div>
                            
                            <!-- Contenido principal -->
                            <div style='padding: 40px 30px;'>
                                <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                                    Hola <strong>" . htmlspecialchars($user['nombre_usuario']) . "</strong>,
                                </p>
                                
                                <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                                    Has solicitado recuperar tu contraseña para SecuriTI.
                                </p>
                                
                                <!-- Botón de recuperación -->
                                <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin: 30px 0;'>
                                    <tr>
                                        <td align='center' style='padding: 0;'>
                                            <table cellpadding='0' cellspacing='0' border='0' style='margin: 0 auto;'>
                                                <tr>
                                                    <td align='center' style='background-color: #1E2A3B; border-radius: 8px; padding: 0;'>
                                                        <a href='" . htmlspecialchars($resetUrl) . "' style='display: inline-block; padding: 15px 30px; background-color: #1E2A3B; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; font-family: Arial, sans-serif; border: 2px solid #1E2A3B;'>Restablecer Contraseña</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Link alternativo
                                <p style='font-size: 14px; color: #6c757d; margin-top: 20px; text-align: center;'>
                                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                    <span style='word-break: break-all; color: #1E2A3B; font-size: 12px;'>" . htmlspecialchars($resetUrl) . "</span>
                                </p> -->
                                
                                <!-- Advertencias -->
                                <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #f39c12;'>
                                    <h4 style='margin: 0 0 10px 0; color: #856404; font-size: 16px;'>
                                        <i style='margin-right: 8px;'>⚠️</i> Importante
                                    </h4>
                                    <ul style='margin: 10px 0 0 20px; color: #856404; font-size: 14px; padding: 0;'>
                                        <li>Este link expirará en <strong>15 minutos</strong></li>
                                        <li>Si no solicitaste este cambio, ignora este correo</li>
                                        <li>No compartas este link con nadie</li>
                                    </ul>
                                </div>
                                
                                <p style='color: #6c757d; font-size: 14px; margin-top: 30px;'>
                                    <strong>⚠️ Seguridad:</strong> Si no fuiste tú quien solicitó este cambio, por favor ignora este correo. Tu cuenta permanecerá segura.
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
                    
                    $mail->Body = $emailBody;
                    $mail->AltBody = "Hola " . $user['nombre_usuario'] . ",\n\n" .
                                    "Has solicitado recuperar tu contraseña para SecuriTI.\n\n" .
                                    "Haz clic en el siguiente enlace para restablecer tu contraseña:\n" .
                                    $resetUrl . "\n\n" .
                                    "Este link expirará en 15 minutos.\n\n" .
                                    "Si no solicitaste este cambio, ignora este correo.\n\n" .
                                    "Saludos,\nEquipo de SecuriTI";
                    
                    if ($mail->send()) {
                        error_log('[Password Reset] Token enviado exitosamente para usuario: ' . $user['nombre_usuario']);
                        $message = $genericMessage;
                        $messageType = 'success';
                    } else {
                        throw new Exception('Error al enviar el correo: ' . $mail->ErrorInfo);
                    }
                } catch (Exception $e) {
                    error_log('[Password Reset] Error al enviar correo: ' . $e->getMessage());
                    // Mostrar mensaje genérico incluso si falla el envío
                    $message = $genericMessage;
                    $messageType = 'success';
                }
            } else {
                // Email no coincide - mensaje genérico
                $message = $genericMessage;
                $messageType = 'success';
            }
        } else {
            // Usuario no existe o no tiene email - mensaje genérico
            $message = $genericMessage;
            $messageType = 'success';
        }
    } catch (Exception $e) {
        error_log('[Password Reset] Error: ' . $e->getMessage());
        $message = $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - SecuriTI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary-color: #1E2A3B;
            --secondary-color: #1E2A3B;
            --black: #000000;
            --white: #ffffff;
            --gray: #efefef;
            --gray-2: #757575;

            --facebook-color: #4267B2;
            --google-color: #DB4437;
            --twitter-color: #1DA1F2;
            --insta-color: #E1306C;
        }

        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600&display=swap');

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
            position: relative;
            z-index: 10;
        }

        .align-items-center {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .form-wrapper {
            width: 100%;
            max-width: 28rem;
            position: relative;
            z-index: 10;
        }

        .form {
            padding: 1rem;
            background-color: var(--white);
            border-radius: 1.5rem;
            width: 100%;
            box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;
            transform: scale(0);
            transition: .5s ease-in-out;
            transition-delay: 1s;
        }

        .container.sign-in .form.sign-in {
            transform: scale(1);
        }

        .input-group {
            position: relative;
            width: 100%;
            margin: 1rem 0;
        }

        .input-group i {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            font-size: 1.4rem;
            color: var(--gray-2);
        }

        .input-group input {
            width: 100%;
            padding: 1rem 3rem;
            font-size: 1rem;
            background-color: var(--gray);
            border-radius: .5rem;
            border: 0.125rem solid var(--white);
            outline: none;
        }

        .input-group input:focus {
            border: 0.125rem solid var(--primary-color);
        }

        .form button {
            cursor: pointer;
            width: 100%;
            border: none;
            padding: .6rem 0;
            border-radius: .5rem;
            background-color: var(--primary-color);
            color: var(--white);
            font-size: 1.2rem;
            outline: none;
        }

        .form p {
            margin: 1rem 0;
            font-size: .7rem;
        }

        .flex-col {
            flex-direction: column;
        }

        .pointer {
            cursor: pointer;
        }

        .content-row {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 6;
            width: 100%;
        }

        .text {
            margin: 4rem;
            color: var(--white);
        }

        .text h2 {
            font-size: 3.5rem;
            font-weight: 800;
            margin: 2rem 0;
            transition: 1s ease-in-out;
        }

        .text h2 img {
            width: 100%;
            max-width: 500px;
            height: auto;
            transition: 1s ease-in-out;
            transition-delay: .4s;
            display: block;
        }
        .text p {
            font-weight: 600;
            transition: 1s ease-in-out;
            transition-delay: .2s;
        }

        .img img {
            width: 30vw;
            transition: 1s ease-in-out;
            transition-delay: .4s;
        }

        .container::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            height: 100vh;
            width: 300vw;
            transform: translate(0, 0);
            right: 50%;
            background-image: linear-gradient(-45deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            transition: 1s ease-in-out;
            z-index: 6;
            box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;
            border-bottom-right-radius: max(50vw, 50vh);
            border-top-left-radius: max(50vw, 50vh);
        }

        .message {
            padding: 15px;
            border-radius: .5rem;
            margin-bottom: 20px;
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

        /* Responsive para tablets */
        @media only screen and (max-width: 1024px) {
            .text h2 img {
                max-width: 400px;
            }
            
            .text {
                margin: 2rem;
            }
        }

        /* Responsive para tablets pequeñas */
        @media only screen and (max-width: 768px) {
            .text h2 img {
                max-width: 350px;
            }
            
            .text {
                margin: 1.5rem;
            }
            
            .text h2 {
                font-size: 2.5rem;
            }
        }

        /* Responsive para móviles */
        @media only screen and (max-width: 425px) {

            .container::before {
                height: 100vh;
                border-bottom-right-radius: 0;
                border-top-left-radius: 0;
                z-index: 0;
                transform: none;
                right: 0;
            }

            .content-row {
                align-items: flex-start !important;
            }

            .content-row .col {
                transform: translateY(0);
                background-color: unset;
            }

            .col {
                width: 100%;
                position: absolute;
                padding: 2rem;
                background-color: var(--white);
                border-top-left-radius: 2rem;
                border-top-right-radius: 2rem;
                transform: translateY(100%);
                transition: 1s ease-in-out;
            }

            .row {
                align-items: flex-end;
                justify-content: flex-end;
            }

            .form {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }

            .text {
                margin: 0;
                padding: 1rem;
            }

            .text p {
                display: none;
            }

            .text h2 {
                margin: .5rem;
                font-size: 1.5rem;
            }
            
            .text h2 img {
                max-width: 280px;
                margin: 0 auto;
            }
        }

        /* Responsive para móviles muy pequeños */
        @media only screen and (max-width: 320px) {
            .text h2 img {
                max-width: 240px;
            }
            
            .text h2 {
                margin: .3rem;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <div id="container" class="container sign-in">
        <div class="row">
            <div class="col align-items-center flex-col sign-up">
                <!-- Columna izquierda vacía para el logo -->
            </div>

            <div class="col align-items-center flex-col sign-in">
                <div class="form-wrapper align-items-center">
                    <div class="form sign-in">
                        <?php if ($message): ?>
                            <div class="message <?php echo $messageType; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="resetForm">
                            <input type="hidden" name="action" value="request_reset">
                            
                            <div class="input-group">
                                <i class='fas fa-user'></i>
                                <input type="text" name="username" placeholder="Nombre de usuario" required autocomplete="username">
                            </div>
                            
                            <div class="input-group">
                                <i class='fas fa-envelope'></i>
                                <input type="email" name="email" placeholder="Correo electrónico" required autocomplete="email">
                            </div>
                            
                            <button type="submit">
                                Enviar Instrucciones
                            </button>
                            
                            <p>
                                <span>
                                    ¿Recordaste tu contraseña?
                                </span>
                                <b onclick="window.location.href='index.php'" class="pointer">
                                    Inicia sesión aquí
                                </b>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row content-row">
            <div class="col align-items-center flex-col">
                <div class="text sign-in">
                    <h2>
                    <img src="Inventario/img/logolargoblanco.png" alt="Login">
                    </h2>
                </div>
                <div class="img sign-in">
                </div>
            </div>
        </div>
    </div>

    <script>
        let container = document.getElementById('container');
        
        setTimeout(() => {
            container.classList.add('sign-in');
        }, 200);
    </script>
</body>
</html>
