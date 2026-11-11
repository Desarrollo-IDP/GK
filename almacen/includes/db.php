<?php
/**
 * Archivo de conexión a la base de datos
 * Sistema de Gestión de Almacén
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de la base de datos
$db_host = 'localhost';
$db_user = 'secureitmxcom_tickets';
$db_pass = 'Becarios.2025';
$db_name = 'grupo_gk';

// Crear conexión
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer charset UTF-8
$conn->set_charset("utf8mb4");

// Zona horaria unificada (PHP + MySQL) para evitar fechas incorrectas
if (!defined('ALMACEN_TIMEZONE')) {
    define('ALMACEN_TIMEZONE', 'America/Mexico_City');
}
date_default_timezone_set(ALMACEN_TIMEZONE);

// Offset dinámico (+/-HH:MM) respeta horario de verano sin tablas tz de MySQL
$mysql_tz_offset = (new DateTime('now', new DateTimeZone(ALMACEN_TIMEZONE)))->format('P');
if (!$conn->query("SET time_zone = '" . $mysql_tz_offset . "'")) {
    $conn->query("SET time_zone = '-06:00'");
}

/**
 * Fecha actual en formato Y-m-d (zona horaria del almacén).
 */
function fechaHoyAlmacen() {
    return (new DateTime('now', new DateTimeZone(ALMACEN_TIMEZONE)))->format('Y-m-d');
}

/**
 * Fecha/hora actual formateada para reportes.
 */
function fechaHoraActualAlmacen($formato = 'd/m/Y H:i') {
    return (new DateTime('now', new DateTimeZone(ALMACEN_TIMEZONE)))->format($formato);
}

/**
 * Formatea fechas DATE/DATETIME de BD sin desfase por strtotime/UTC.
 */
function formatearFechaAlmacen($fecha, $formato = 'd/m/Y') {
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $tz = new DateTimeZone(ALMACEN_TIMEZONE);
    $fechaStr = trim((string) $fecha);

    // DATE puro (Y-m-d): sin strtotime para evitar desfase de un día por UTC
    if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $fechaStr, $m)) {
        $dt = DateTime::createFromFormat('Y-m-d', $m[1], $tz);
        if ($dt instanceof DateTime) {
            return $dt->format($formato);
        }
    }

    // DATETIME de MySQL
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)/', $fechaStr, $m)) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $m[1] . ' ' . (strlen($m[2]) === 5 ? $m[2] . ':00' : $m[2]), $tz);
        if ($dt instanceof DateTime) {
            return $dt->format($formato);
        }
    }

    try {
        return (new DateTime($fechaStr, $tz))->format($formato);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Valida y normaliza una fecha a Y-m-d (zona del almacén).
 */
function normalizarFechaAlmacen($fecha) {
    if ($fecha === null || $fecha === '') {
        return null;
    }
    $fechaStr = trim((string) $fecha);
    $tz = new DateTimeZone(ALMACEN_TIMEZONE);

    if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $fechaStr, $m)) {
        $dt = DateTime::createFromFormat('Y-m-d', $m[1], $tz);
        if ($dt && $dt->format('Y-m-d') === $m[1]) {
            return $m[1];
        }
    }

    return null;
}

/**
 * Fecha/hora actual para guardar en MySQL (Y-m-d H:i:s).
 */
function ahoraMysqlAlmacen() {
    return (new DateTime('now', new DateTimeZone(ALMACEN_TIMEZONE)))->format('Y-m-d H:i:s');
}

// Función para limpiar datos de entrada
// Nota: htmlspecialchars() se debe aplicar solo en la SALIDA, no en la entrada
// real_escape_string() NO se debe usar con prepared statements
function limpiarDatos($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

// Función para escapar datos en la salida HTML
function escaparHTML($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Función para validar sesión
function validarSesion() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user'])) {
        header('Location: ../../index.php');
        exit();
    }
}
?>
