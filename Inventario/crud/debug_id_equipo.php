<?php
/**
 * Archivo de Debug para investigar problema con id_equipo
 * Este archivo ayuda a diagnosticar por qué id_equipo no se está generando correctamente
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../includes/empresa_helper.php';
require_once __DIR__ . '/../includes/equipment_counter_helper.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

echo "<h1>Debug: Investigación de id_equipo</h1>";
echo "<pre>";

// 1. Verificar estructura de la tabla equipos
echo "=== 1. ESTRUCTURA DE LA TABLA equipos ===\n";
$structure = $conn->query("DESCRIBE equipos");
if ($structure) {
    echo "Columnas encontradas:\n";
    while ($col = $structure->fetch_assoc()) {
        echo sprintf(
            "  - %s: %s %s %s %s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL',
            $col['Key'] ? "({$col['Key']})" : '',
            $col['Extra'] ? "({$col['Extra']})" : ''
        );
    }
} else {
    echo "ERROR: No se pudo obtener la estructura de la tabla\n";
    echo "Error: " . $conn->error . "\n";
}

echo "\n=== 2. VERIFICAR AUTO_INCREMENT ===\n";
$auto_increment = $conn->query("SHOW TABLE STATUS LIKE 'equipos'");
if ($auto_increment) {
    $status = $auto_increment->fetch_assoc();
    echo "Auto_increment actual: " . ($status['Auto_increment'] ?? 'NO CONFIGURADO') . "\n";
    echo "Engine: " . ($status['Engine'] ?? 'N/A') . "\n";
} else {
    echo "ERROR: No se pudo obtener el estado de la tabla\n";
}

echo "\n=== 3. VERIFICAR ÚLTIMO ID_equipo ===\n";
$last_id = $conn->query("SELECT MAX(id_equipo) as max_id FROM equipos");
if ($last_id) {
    $row = $last_id->fetch_assoc();
    echo "Último id_equipo en la tabla: " . ($row['max_id'] ?? 'NINGUNO') . "\n";
} else {
    echo "ERROR: No se pudo obtener el último ID\n";
}

echo "\n=== 4. VERIFICAR EMPRESA DEL USUARIO ===\n";
echo "User empresa ID: " . ($user_empresa_id ?? 'NULL (Admin)') . "\n";

echo "\n=== 5. PRUEBA DE INSERCIÓN ===\n";
echo "Intentando insertar un equipo de prueba...\n";

// Verificar si existe columna id_empresa_usuario
$check_column_equipos = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column_equipos = $check_column_equipos && $check_column_equipos->num_rows > 0;
echo "Columna id_empresa_usuario existe: " . ($has_empresa_usuario_column_equipos ? 'SÍ' : 'NO') . "\n";

// Verificar si existe columna equipo_number
$check_column_equipo_number = $conn->query("SHOW COLUMNS FROM equipos LIKE 'equipo_number'");
$has_equipo_number_column = $check_column_equipo_number && $check_column_equipo_number->num_rows > 0;
echo "Columna equipo_number existe: " . ($has_equipo_number_column ? 'SÍ' : 'NO') . "\n";

if ($has_empresa_usuario_column_equipos && $user_empresa_id !== null) {
    // Obtener el siguiente número de equipo
    try {
        $equipo_number = getNextEquipmentNumber($conn, $user_empresa_id);
        echo "Número de equipo obtenido: " . $equipo_number . "\n";
    } catch (Exception $e) {
        echo "ERROR al obtener número de equipo: " . $e->getMessage() . "\n";
        $equipo_number = null;
    }
} else {
    echo "No se puede obtener número de equipo (usuario sin empresa o columna no existe)\n";
    $equipo_number = null;
}

// Intentar insertar un equipo de prueba
$conn->begin_transaction();

try {
    $test_nombre_cliente = "TEST DEBUG";
    $test_nombre_equipo = "Equipo Debug " . date('Y-m-d H:i:s');
    $test_responsable = "Debug User";
    $test_sistema_operativo = "Windows 10";
    $test_direccion_mac = "00:00:00:00:00:00";
    $test_tipo = "Desktop";
    $test_fabricante = "TEST";
    $test_modelo = "TEST MODEL";
    $test_numero_serie = "TEST123";
    $test_ram = "8GB";
    $test_disco_duro = "500GB";
    $test_procesador = "Intel Test";
    $test_observaciones = "Equipo de prueba para debug";
    $test_id_empresa = 1; // Usar empresa ID 1 como prueba
    $test_documentos = null;
    
    echo "\n--- Preparando INSERT ---\n";
    
    if ($has_equipo_number_column && $has_empresa_usuario_column_equipos && $user_empresa_id !== null && $equipo_number !== null) {
        echo "INSERT con id_empresa_usuario y equipo_number\n";
        $stmt = $conn->prepare("INSERT INTO equipos (
            nombre_cliente, 
            nombre_equipo, 
            responsable, 
            sistema_operativo, 
            direccion_mac, 
            tipo, 
            fabricante, 
            modelo, 
            numero_serie, 
            ram, 
            disco_duro, 
            procesador, 
            observaciones, 
            id_empresa,
            id_empresa_usuario,
            equipo_number,
            documentos
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Error al preparar INSERT: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssssssssssiss", 
            $test_nombre_cliente,
            $test_nombre_equipo,
            $test_responsable,
            $test_sistema_operativo,
            $test_direccion_mac,
            $test_tipo,
            $test_fabricante,
            $test_modelo,
            $test_numero_serie,
            $test_ram,
            $test_disco_duro,
            $test_procesador,
            $test_observaciones,
            $test_id_empresa,
            $user_empresa_id,
            $equipo_number,
            $test_documentos
        );
    } elseif ($has_empresa_usuario_column_equipos && $user_empresa_id !== null) {
        echo "INSERT con id_empresa_usuario sin equipo_number\n";
        $stmt = $conn->prepare("INSERT INTO equipos (
            nombre_cliente, 
            nombre_equipo, 
            responsable, 
            sistema_operativo, 
            direccion_mac, 
            tipo, 
            fabricante, 
            modelo, 
            numero_serie, 
            ram, 
            disco_duro, 
            procesador, 
            observaciones, 
            id_empresa,
            id_empresa_usuario,
            documentos
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Error al preparar INSERT: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssssssssssis", 
            $test_nombre_cliente,
            $test_nombre_equipo,
            $test_responsable,
            $test_sistema_operativo,
            $test_direccion_mac,
            $test_tipo,
            $test_fabricante,
            $test_modelo,
            $test_numero_serie,
            $test_ram,
            $test_disco_duro,
            $test_procesador,
            $test_observaciones,
            $test_id_empresa,
            $user_empresa_id,
            $test_documentos
        );
    } else {
        echo "INSERT sin id_empresa_usuario\n";
        $stmt = $conn->prepare("INSERT INTO equipos (
            nombre_cliente, 
            nombre_equipo, 
            responsable, 
            sistema_operativo, 
            direccion_mac, 
            tipo, 
            fabricante, 
            modelo, 
            numero_serie, 
            ram, 
            disco_duro, 
            procesador, 
            observaciones, 
            id_empresa,
            documentos
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Error al preparar INSERT: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssssssssss", 
            $test_nombre_cliente,
            $test_nombre_equipo,
            $test_responsable,
            $test_sistema_operativo,
            $test_direccion_mac,
            $test_tipo,
            $test_fabricante,
            $test_modelo,
            $test_numero_serie,
            $test_ram,
            $test_disco_duro,
            $test_procesador,
            $test_observaciones,
            $test_id_empresa,
            $test_documentos
        );
    }
    
    echo "Ejecutando INSERT...\n";
    $execute_result = $stmt->execute();
    
    if (!$execute_result) {
        throw new Exception("Error al ejecutar INSERT: " . $stmt->error);
    }
    
    echo "INSERT ejecutado exitosamente\n";
    echo "Affected rows: " . $stmt->affected_rows . "\n";
    
    // Obtener el ID insertado
    $inserted_id = $conn->insert_id;
    echo "\n=== RESULTADO ===\n";
    echo "insert_id obtenido: " . ($inserted_id ?? 'NULL') . "\n";
    echo "insert_id es 0: " . ($inserted_id == 0 ? 'SÍ' : 'NO') . "\n";
    echo "insert_id es null: " . ($inserted_id === null ? 'SÍ' : 'NO') . "\n";
    
    // Verificar que el registro se insertó correctamente
    if ($inserted_id > 0) {
        $verify = $conn->prepare("SELECT id_equipo, equipo_number, nombre_equipo FROM equipos WHERE id_equipo = ?");
        $verify->bind_param("i", $inserted_id);
        $verify->execute();
        $verify_result = $verify->get_result();
        $equipo_verificado = $verify_result->fetch_assoc();
        
        echo "\n=== VERIFICACIÓN DEL REGISTRO INSERTADO ===\n";
        if ($equipo_verificado) {
            echo "Registro encontrado:\n";
            echo "  id_equipo: " . ($equipo_verificado['id_equipo'] ?? 'NULL') . "\n";
            echo "  equipo_number: " . ($equipo_verificado['equipo_number'] ?? 'NULL') . "\n";
            echo "  nombre_equipo: " . ($equipo_verificado['nombre_equipo'] ?? 'NULL') . "\n";
        } else {
            echo "ERROR: No se encontró el registro con id_equipo = $inserted_id\n";
        }
        $verify->close();
    }
    
    // Hacer rollback para no dejar datos de prueba
    $conn->rollback();
    echo "\n=== TRANSACCIÓN REVERTIDA (rollback) ===\n";
    echo "El equipo de prueba NO se guardó en la base de datos\n";
    
    $stmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n=== ERROR ===\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== 6. VERIFICAR CONFIGURACIÓN DE AUTO_INCREMENT ===\n";
$check_auto = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos'");
if ($check_auto) {
    $auto_row = $check_auto->fetch_assoc();
    echo "AUTO_INCREMENT configurado: " . ($auto_row['AUTO_INCREMENT'] ?? 'NO') . "\n";
} else {
    echo "No se pudo verificar AUTO_INCREMENT\n";
}

echo "\n=== 7. VERIFICAR ÚLTIMOS 5 REGISTROS ===\n";
$last_records = $conn->query("SELECT id_equipo, equipo_number, nombre_equipo, nombre_cliente FROM equipos ORDER BY id_equipo DESC LIMIT 5");
if ($last_records) {
    while ($rec = $last_records->fetch_assoc()) {
        echo sprintf(
            "ID: %s | Número: %s | Equipo: %s | Cliente: %s\n",
            $rec['id_equipo'] ?? 'NULL',
            $rec['equipo_number'] ?? 'NULL',
            $rec['nombre_equipo'] ?? 'NULL',
            $rec['nombre_cliente'] ?? 'NULL'
        );
    }
} else {
    echo "No se pudieron obtener los últimos registros\n";
}

echo "</pre>";
echo "<p><a href='visualizar-equipos.php'>Volver a Visualizar Equipos</a></p>";
?>

