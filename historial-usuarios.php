<?php
require_once __DIR__ . '/auth.php';
checkAuth();

// Verificar que solo el admin pueda acceder
if (!canViewUserHistory()) {
    header('Location: ' . appUrl('/direc.php'));
    exit;
}

// Incluir configuración de base de datos
require_once __DIR__ . '/Inventario/crud/includes/db.php';

// Asegurar que la tabla de historial existe con la nueva estructura
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_history'");
    if ($tableCheck && $tableCheck->num_rows === 0) {
        $createTable = "CREATE TABLE user_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50) NOT NULL,
            target_user VARCHAR(100) NOT NULL,
            target_user_id INT,
            nombre_usuario VARCHAR(100) NOT NULL,
            id_usuario INT,
            description TEXT,
            old_values JSON,
            new_values JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($createTable);
    } else {
        // Verificar si las columnas nuevas existen y agregarlas si es necesario
        $columnCheck1 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'nombre_usuario'");
        $columnCheck2 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'id_usuario'");
        
        if ($columnCheck1 && $columnCheck1->num_rows === 0) {
            $conn->query("ALTER TABLE user_history ADD COLUMN nombre_usuario VARCHAR(100) NOT NULL AFTER target_user_id");
        }
        if ($columnCheck2 && $columnCheck2->num_rows === 0) {
            $conn->query("ALTER TABLE user_history ADD COLUMN id_usuario INT AFTER nombre_usuario");
        }
        
        // Eliminar solo las foreign keys de la tabla (no PRIMARY ni otros índices)
        try {
            // Obtener solo las foreign keys reales de la tabla
            $fkQuery = "SELECT DISTINCT CONSTRAINT_NAME 
                       FROM information_schema.KEY_COLUMN_USAGE 
                       WHERE TABLE_NAME = 'user_history' 
                       AND TABLE_SCHEMA = DATABASE()
                       AND CONSTRAINT_NAME IS NOT NULL
                       AND CONSTRAINT_NAME != 'PRIMARY'
                       AND CONSTRAINT_NAME IN (
                           SELECT CONSTRAINT_NAME 
                           FROM information_schema.REFERENTIAL_CONSTRAINTS 
                           WHERE TABLE_NAME = 'user_history' 
                           AND TABLE_SCHEMA = DATABASE()
                       )";
            $fkResult = $conn->query($fkQuery);
            
            if ($fkResult && $fkResult->num_rows > 0) {
                while ($fkRow = $fkResult->fetch_assoc()) {
                    $constraintName = $fkRow['CONSTRAINT_NAME'];
                    try {
                        $conn->query("ALTER TABLE user_history DROP FOREIGN KEY `$constraintName`");
                    } catch (Exception $dropError) {
                        error_log("Error eliminando FK $constraintName: " . $dropError->getMessage());
                    }
                }
            }
        } catch (Exception $fkError) {
            error_log('Error obteniendo foreign keys: ' . $fkError->getMessage());
        }
        
        // Eliminar columnas antiguas si existen
        $oldColumnCheck1 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'performed_by'");
        $oldColumnCheck2 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'performed_by_id'");
        
        if ($oldColumnCheck1 && $oldColumnCheck1->num_rows > 0) {
            $conn->query("ALTER TABLE user_history DROP COLUMN performed_by");
        }
        if ($oldColumnCheck2 && $oldColumnCheck2->num_rows > 0) {
            $conn->query("ALTER TABLE user_history DROP COLUMN performed_by_id");
        }
    }
} catch (Exception $e) {
    error_log('Error creando tabla user_history: ' . $e->getMessage());
}

// Obtener el historial de usuarios
$query = "SELECT * FROM user_history ORDER BY created_at DESC";
$result = $conn->query($query);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Usuarios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1E2A3B;
            --secondary-color: #2C3E50;
        }
        
        .custom-bg {
            background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color));
        }
        
        .history-item {
            border-left: 4px solid #e9ecef;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .history-item.created { border-left-color: #28a745; }
        .history-item.updated { border-left-color: #007bff; }
        .history-item.deleted { border-left-color: #dc3545; }
        
        .history-icon {
            position: absolute;
            left: -12px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: white;
        }
        .history-item.created .history-icon { background-color: #28a745; }
        .history-item.updated .history-icon { background-color: #007bff; }
        .history-item.deleted .history-icon { background-color: #dc3545; }
        
        .json-display {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.5rem;
            font-family: monospace;
            font-size: 0.875rem;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="custom-bg text-white py-4 mb-6">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-history mr-2"></i>
                Historial de Usuarios
            </h1>
            <a href="<?php echo htmlspecialchars(appUrl('/gestion-usuarios.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-gray-800 px-4 py-2 rounded hover:bg-gray-100 transition">  
                <i class="fas fa-arrow-left mr-2"></i>
                Volver a Gestión de Usuarios
            </a>
        </div>
    </div>

    <div class="container mx-auto px-4">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">Registro de Actividades</h2>
                <div class="text-sm text-gray-500">
                    Total de registros: <?php echo $result->num_rows; ?>
                </div>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($history = $result->fetch_assoc()): ?>
                        <div class="history-item <?php echo $history['action']; ?>">
                            <div class="history-icon">
                                <?php
                                switch($history['action']) {
                                    case 'created': echo '<i class="fas fa-user-plus"></i>'; break;
                                    case 'updated': echo '<i class="fas fa-user-edit"></i>'; break;
                                    case 'deleted': echo '<i class="fas fa-user-times"></i>'; break;
                                }
                                ?>
                            </div>
                            
                            <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="text-lg font-semibold">
                                        <?php
                                        switch($history['action']) {
                                            case 'created': echo 'Usuario Creado'; break;
                                            case 'updated': echo 'Usuario Actualizado'; break;
                                            case 'deleted': echo 'Usuario Eliminado'; break;
                                        }
                                        ?>
                                    </h3>
                                    <span class="text-sm text-gray-500">
                                        <?php echo date('d/m/Y H:i:s', strtotime($history['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <p class="text-sm text-gray-600"><strong>Usuario afectado:</strong></p>
                                        <p class="font-medium"><?php echo htmlspecialchars($history['target_user']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600"><strong>Acción realizada por:</strong></p>
                                        <p class="font-medium"><?php echo htmlspecialchars($history['nombre_usuario']); ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($history['description'])): ?>
                                    <div class="mb-3">
                                        <p class="text-sm text-gray-600"><strong>Descripción:</strong></p>
                                        <p><?php echo htmlspecialchars($history['description']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($history['action'] === 'updated' && (!empty($history['old_values']) || !empty($history['new_values']))): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <?php if (!empty($history['old_values'])): ?>
                                            <div>
                                                <p class="text-sm text-gray-600 mb-2"><strong>Valores anteriores:</strong></p>
                                                <div class="json-display">
                                                    <?php 
                                                    $oldValues = json_decode($history['old_values'], true);
                                                    if ($oldValues) {
                                                        foreach ($oldValues as $key => $value) {
                                                            $displayKey = $key;
                                                            $displayValue = $value;
                                                            
                                                            // Convertir nombres de campos a etiquetas legibles
                                                            switch($key) {
                                                                case 'can_view_inventory_history':
                                                                    $displayKey = 'Ver historial inventario';
                                                                    $displayValue = ($value == 1) ? 'SÍ' : 'NO';
                                                                    break;
                                                                case 'can_view_ticket_history':
                                                                    $displayKey = 'Ver historial tickets';
                                                                    $displayValue = ($value == 1) ? 'SÍ' : 'NO';
                                                                    break;
                                                                case 'can_manage_users':
                                                                    $displayKey = 'Gestión de usuarios';
                                                                    $displayValue = ($value == 1) ? 'SÍ' : 'NO';
                                                                    break;
                                                            }
                                                            
                                                            echo "<div><strong>$displayKey:</strong> " . htmlspecialchars($displayValue) . "</div>";
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($history['new_values'])): ?>
                                            <div>
                                                <p class="text-sm text-gray-600 mb-2"><strong>Valores nuevos:</strong></p>
                                                <div class="json-display">
                                                    <?php 
                                                    $newValues = json_decode($history['new_values'], true);
                                                    if ($newValues) {
                                                        foreach ($newValues as $key => $value) {
                                                            $displayKey = $key;
                                                            $displayValue = $value;
                                                            
                                                            // Convertir nombres de campos a etiquetas legibles
                                                            switch($key) {
                                                                case 'can_view_inventory_history':
                                                                    $displayKey = 'Ver historial inventario';
                                                                    $displayValue = ($value == 1) ? 'SÍ' : 'NO';
                                                                    break;
                                                                case 'can_view_ticket_history':
                                                                    $displayKey = 'Ver historial tickets';
                                                                    $displayValue = ($value == 1) ? 'SÍ' : 'NO';
                                                                    break;
                                                                case 'can_manage_users':
                                                                    $displayKey = 'Gestión de usuarios';
                                                                    $displayValue = ($value == 1) ? 'SÍ' : 'NO';
                                                                    break;
                                                            }
                                                            
                                                            echo "<div><strong>$displayKey:</strong> " . htmlspecialchars($displayValue) . "</div>";
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-history fa-3x text-gray-400 mb-4"></i>
                    <p class="text-gray-500 text-lg">No hay historial de usuarios disponible</p>
                    <p class="text-gray-400 text-sm">Las actividades de usuarios aparecerán aquí</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh cada 30 segundos para mantener el historial actualizado
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
