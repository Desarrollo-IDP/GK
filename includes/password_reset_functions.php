<?php
/**
 * Funciones auxiliares para el sistema de recuperación de contraseñas
 */

require_once __DIR__ . '/../Inventario/crud/includes/db.php';

/**
 * Genera un token seguro y lo guarda en la base de datos
 * @param int $userId ID del usuario
 * @param mysqli $db Conexión a la base de datos
 * @return string Token generado (64 caracteres hexadecimales)
 */
function generateResetToken($userId, $db) {
    // Generar token aleatorio seguro (64 caracteres)
    $token = bin2hex(random_bytes(32));
    
    // Crear hash del token (SHA-256)
    $tokenHash = hash('sha256', $token);
    
    // Calcular fecha de expiración (15 minutos desde ahora)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Invalidar tokens previos del usuario
    invalidateUserTokens($userId, $db);
    
    // Insertar nuevo token
    $stmt = $db->prepare('INSERT INTO password_reset_tokens (id_usuario, token_hash, expires_at, used) VALUES (?, ?, ?, 0)');
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $db->error);
    }
    
    $stmt->bind_param('iss', $userId, $tokenHash, $expiresAt);
    if (!$stmt->execute()) {
        throw new Exception('Error al guardar el token: ' . $stmt->error);
    }
    
    return $token;
}

/**
 * Valida un token de recuperación
 * @param string $token Token a validar
 * @param mysqli $db Conexión a la base de datos
 * @return array|null Datos del token si es válido, null si no lo es
 */
function validateResetToken($token, $db) {
    if (empty($token)) {
        return null;
    }
    
    // Calcular hash del token recibido
    $tokenHash = hash('sha256', $token);
    
    // Buscar token en la BD
    $stmt = $db->prepare('SELECT id, id_usuario, expires_at, used FROM password_reset_tokens WHERE token_hash = ? LIMIT 1');
    if (!$stmt) {
        error_log('Error al preparar consulta de validación de token: ' . $db->error);
        return null;
    }
    
    $stmt->bind_param('s', $tokenHash);
    if (!$stmt->execute()) {
        error_log('Error al ejecutar consulta de validación de token: ' . $stmt->error);
        return null;
    }
    
    $result = $stmt->get_result();
    $tokenData = $result->fetch_assoc();
    
    if (!$tokenData) {
        return null;
    }
    
    // Verificar que no esté usado
    if ($tokenData['used'] == 1) {
        return null;
    }
    
    // Verificar que no esté expirado
    $now = new DateTime();
    $expiresAt = new DateTime($tokenData['expires_at']);
    if ($now > $expiresAt) {
        return null;
    }
    
    return $tokenData;
}

/**
 * Invalida todos los tokens pendientes de un usuario
 * @param int $userId ID del usuario
 * @param mysqli $db Conexión a la base de datos
 */
function invalidateUserTokens($userId, $db) {
    $stmt = $db->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id_usuario = ? AND used = 0');
    if (!$stmt) {
        error_log('Error al preparar consulta de invalidación: ' . $db->error);
        return;
    }
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}

/**
 * Marca un token como usado
 * @param string $tokenHash Hash del token
 * @param mysqli $db Conexión a la base de datos
 */
function markTokenAsUsed($tokenHash, $db) {
    $stmt = $db->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token_hash = ?');
    if (!$stmt) {
        error_log('Error al preparar consulta de marcar token como usado: ' . $db->error);
        return;
    }
    
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
}

/**
 * Limpia tokens expirados de la base de datos
 * @param mysqli $db Conexión a la base de datos
 */
function cleanExpiredTokens($db) {
    $stmt = $db->prepare('DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR (used = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))');
    if (!$stmt) {
        error_log('Error al preparar consulta de limpieza: ' . $db->error);
        return;
    }
    
    $stmt->execute();
}
