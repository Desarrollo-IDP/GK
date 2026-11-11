<?php
/**
 * Script para agregar identificador único a clientes (VERSIÓN LOCAL)
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

echo "=== AGREGANDO IDENTIFICADOR ÚNICO PARA CLIENTES (LOCAL) ===\n\n";

try {
    // 1. Verificar conexión a la base de datos local
    echo "1. Verificando conexión a la base de datos...\n";
    $pdo = getConnection();
    echo "✅ Conexión establecida\n\n";
    
    // 2. Verificar si existe el campo identificador_unico
    echo "2. Verificando campo identificador_unico...\n";
    
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
    
    // 3. Generar identificadores únicos para clientes existentes
    echo "3. Generando identificadores únicos para clientes existentes...\n";
    
    $stmt = $pdo->query("SELECT id, nombre, apellido_paterno, empresa_id FROM clientes WHERE identificador_unico IS NULL");
    $clientesSinIdentificador = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($clientesSinIdentificador) > 0) {
        echo "   Encontrados " . count($clientesSinIdentificador) . " clientes sin identificador\n";
        
        foreach ($clientesSinIdentificador as $cliente) {
            // Generar identificador único usando la función del sistema
            $identificador = generateClienteIdentificador($cliente['empresa_id'], $cliente['id']);
            
            $updateStmt = $pdo->prepare("UPDATE clientes SET identificador_unico = ? WHERE id = ?");
            $updateStmt->execute([$identificador, $cliente['id']]);
            
            echo "   ✅ Cliente {$cliente['nombre']} {$cliente['apellido_paterno']}: $identificador\n";
        }
    } else {
        echo "   ✅ Todos los clientes ya tienen identificador único\n";
    }
    echo "\n";
    
    // 4. Agregar índice para optimizar búsquedas
    echo "4. Verificando índice para identificador_unico...\n";
    
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
    
    // 5. Verificar estructura final
    echo "5. Verificando estructura final...\n";
    
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
    
    // Mostrar algunos ejemplos de identificadores
    if ($clientesConIdentificador > 0) {
        echo "\n   Ejemplos de identificadores generados:\n";
        $stmt = $pdo->query("SELECT nombre, apellido_paterno, identificador_unico FROM clientes WHERE identificador_unico IS NOT NULL LIMIT 3");
        $ejemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ejemplos as $ejemplo) {
            echo "   • {$ejemplo['nombre']} {$ejemplo['apellido_paterno']}: {$ejemplo['identificador_unico']}\n";
        }
    }
    
    echo "\n";
    
    // 6. Resumen
    echo "=== RESUMEN ===\n";
    echo "✅ Campo identificador_unico: Verificado\n";
    echo "✅ Identificadores únicos: Generados\n";
    echo "✅ Índice de optimización: Verificado\n";
    echo "✅ Sistema de tracking: Listo\n";
    
    echo "\n=== FUNCIONALIDAD IMPLEMENTADA ===\n";
    echo "• Cada cliente ahora tiene un identificador único\n";
    echo "• Formato: EMP{empresa_id}_CLI{cliente_id}_{timestamp}_{random}\n";
    echo "• Permite tracking automático de pagos\n";
    echo "• Se puede usar como external_reference en MercadoPago\n";
    
    echo "\n=== PRÓXIMOS PASOS ===\n";
    echo "1. Probar el sistema de membresías\n";
    echo "2. Verificar que los webhooks funcionen correctamente\n";
    echo "3. Probar el flujo completo de pago y actualización\n";
    
    echo "\n=== CAMBIOS APLICADOS EXITOSAMENTE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error aplicando cambios: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    echo "\n=== SOLUCIÓN DE PROBLEMAS ===\n";
    echo "1. Verifica que la conexión a la base de datos esté configurada\n";
    echo "2. Revisa las credenciales en includes/db.php\n";
}
?>
