<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

initConfig();

echo "=== AGREGANDO IDENTIFICADOR ÚNICO PARA CLIENTES ===\n\n";

try {
    // 1. Verificar si existe el campo identificador_unico
    echo "1. Verificando campo identificador_unico...\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM `clientes` LIKE 'identificador_unico'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "   Campo no existe, agregando...\n";
        $pdo->exec("ALTER TABLE `clientes` ADD COLUMN `identificador_unico` VARCHAR(50) UNIQUE NULL COMMENT 'Identificador único para tracking de pagos' AFTER `empresa_id`");
        echo "✅ Campo identificador_unico agregado\n";
    } else {
        echo "✅ Campo identificador_unico ya existe\n";
    }
    echo "\n";
    
    // 2. Generar identificadores únicos para clientes existentes
    echo "2. Generando identificadores únicos para clientes existentes...\n";
    
    $stmt = $pdo->query("SELECT id, nombre, apellido_paterno, empresa_id FROM clientes WHERE identificador_unico IS NULL");
    $clientesSinIdentificador = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($clientesSinIdentificador) > 0) {
        echo "   Encontrados " . count($clientesSinIdentificador) . " clientes sin identificador\n";
        
        foreach ($clientesSinIdentificador as $cliente) {
            // Generar identificador único: EMP{empresa_id}_CLI{cliente_id}_{timestamp}
            $identificador = 'EMP' . $cliente['empresa_id'] . '_CLI' . $cliente['id'] . '_' . time();
            
            $updateStmt = $pdo->prepare("UPDATE clientes SET identificador_unico = ? WHERE id = ?");
            $updateStmt->execute([$identificador, $cliente['id']]);
            
            echo "   ✅ Cliente {$cliente['nombre']} {$cliente['apellido_paterno']}: $identificador\n";
        }
    } else {
        echo "   ✅ Todos los clientes ya tienen identificador único\n";
    }
    echo "\n";
    
    // 3. Agregar índice para optimizar búsquedas
    echo "3. Verificando índice para identificador_unico...\n";
    
    $stmt = $pdo->query("SHOW INDEX FROM `clientes` WHERE Key_name = 'idx_clientes_identificador'");
    $indexExists = $stmt->rowCount() > 0;
    
    if (!$indexExists) {
        echo "   Índice no existe, agregando...\n";
        $pdo->exec("ALTER TABLE `clientes` ADD INDEX `idx_clientes_identificador` (`identificador_unico`)");
        echo "✅ Índice idx_clientes_identificador agregado\n";
    } else {
        echo "✅ Índice idx_clientes_identificador ya existe\n";
    }
    echo "\n";
    
    // 4. Verificar estructura final
    echo "4. Verificando estructura final...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes");
    $totalClientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as con_identificador FROM clientes WHERE identificador_unico IS NOT NULL");
    $clientesConIdentificador = $stmt->fetch(PDO::FETCH_ASSOC)['con_identificador'];
    
    echo "   Total de clientes: $totalClientes\n";
    echo "   Clientes con identificador: $clientesConIdentificador\n";
    
    if ($totalClientes == $clientesConIdentificador) {
        echo "   ✅ Todos los clientes tienen identificador único\n";
    } else {
        echo "   ⚠️  Algunos clientes no tienen identificador único\n";
    }
    
    echo "\n";
    
    // 5. Resumen
    echo "=== RESUMEN ===\n";
    echo "✅ Campo identificador_unico: Agregado\n";
    echo "✅ Identificadores únicos: Generados\n";
    echo "✅ Índice de optimización: Agregado\n";
    echo "✅ Sistema de tracking: Listo\n";
    
    echo "\n=== FUNCIONALIDAD IMPLEMENTADA ===\n";
    echo "• Cada cliente ahora tiene un identificador único\n";
    echo "• Formato: EMP{empresa_id}_CLI{cliente_id}_{timestamp}\n";
    echo "• Permite tracking automático de pagos\n";
    echo "• Se puede usar como external_reference en MercadoPago\n";
    
    echo "\n=== CAMBIOS APLICADOS EXITOSAMENTE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error aplicando cambios: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
