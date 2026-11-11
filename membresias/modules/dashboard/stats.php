<?php
/**
 * Estadísticas detalladas para el dashboard
 * Este archivo proporciona funciones para obtener estadísticas del sistema
 */

include '../../includes/db.php';

/**
 * Obtener estadísticas generales del sistema
 */
function getGeneralStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total de membresías
        $sql = "SELECT COUNT(*) as total FROM membresias";
        $stmt = $pdo->query($sql);
        $stats['total_membresias'] = $stmt->fetch()['total'];
        
        // Membresías activas
        $sql = "SELECT COUNT(*) as activas FROM membresias WHERE fecha_fin >= CURDATE()";
        $stmt = $pdo->query($sql);
        $stats['membresias_activas'] = $stmt->fetch()['activas'];
        
        // Membresías expiradas
        $sql = "SELECT COUNT(*) as expiradas FROM membresias WHERE fecha_fin < CURDATE()";
        $stmt = $pdo->query($sql);
        $stats['membresias_expiradas'] = $stmt->fetch()['expiradas'];
        
        // Membresías por expirar (próximos 7 días)
        $sql = "SELECT COUNT(*) as por_expirar FROM membresias WHERE fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $stmt = $pdo->query($sql);
        $stats['membresias_por_expirar'] = $stmt->fetch()['por_expirar'];
        
        // Total de clientes únicos
        $sql = "SELECT COUNT(DISTINCT cliente_id) as clientes_unicos FROM membresias";
        $stmt = $pdo->query($sql);
        $stats['clientes_unicos'] = $stmt->fetch()['clientes_unicos'];
        
        return $stats;
    } catch (PDOException $e) {
        error_log('Error al obtener estadísticas generales: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener estadísticas de ingresos
 */
function getRevenueStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Ingresos del día actual
        $sql = "SELECT COALESCE(SUM(tm.precio), 0) as ingresos_hoy 
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                WHERE DATE(m.created_at) = CURDATE()";
        $stmt = $pdo->query($sql);
        $stats['ingresos_hoy'] = $stmt->fetch()['ingresos_hoy'];
        
        // Ingresos del mes actual
        $sql = "SELECT COALESCE(SUM(tm.precio), 0) as ingresos_mes 
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                WHERE MONTH(m.created_at) = MONTH(CURDATE()) 
                AND YEAR(m.created_at) = YEAR(CURDATE())";
        $stmt = $pdo->query($sql);
        $stats['ingresos_mes'] = $stmt->fetch()['ingresos_mes'];
        
        // Ingresos del año actual
        $sql = "SELECT COALESCE(SUM(tm.precio), 0) as ingresos_anio 
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                WHERE YEAR(m.created_at) = YEAR(CURDATE())";
        $stmt = $pdo->query($sql);
        $stats['ingresos_anio'] = $stmt->fetch()['ingresos_anio'];
        
        // Promedio de ingresos por día (últimos 30 días)
        $sql = "SELECT COALESCE(AVG(ingresos_diarios), 0) as promedio_diario 
                FROM (
                    SELECT DATE(created_at) as fecha, SUM(tm.precio) as ingresos_diarios
                    FROM membresias m 
                    JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                    WHERE m.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                ) as ingresos_diarios";
        $stmt = $pdo->query($sql);
        $stats['promedio_diario'] = $stmt->fetch()['promedio_diario'];
        
        return $stats;
    } catch (PDOException $e) {
        error_log('Error al obtener estadísticas de ingresos: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener datos para gráfica de membresías por día (últimos 7 días)
 */
function getMembershipsByDay($days = 7) {
    global $pdo;
    
    try {
        $sql = "SELECT DATE(created_at) as fecha, COUNT(*) as cantidad 
                FROM membresias 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                GROUP BY DATE(created_at) 
                ORDER BY fecha";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error al obtener datos de membresías por día: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener datos para gráfica de ingresos por día (últimos 7 días)
 */
function getRevenueByDay($days = 7) {
    global $pdo;
    
    try {
        $sql = "SELECT DATE(m.created_at) as fecha, COALESCE(SUM(tm.precio), 0) as ingresos 
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                WHERE m.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                GROUP BY DATE(m.created_at) 
                ORDER BY fecha";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error al obtener datos de ingresos por día: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener top tipos de membresía más populares
 */
function getTopMembershipTypes($limit = 5) {
    global $pdo;
    
    try {
        $sql = "SELECT tm.nombre, COUNT(*) as cantidad, SUM(tm.precio) as ingresos_totales 
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                GROUP BY tm.id, tm.nombre 
                ORDER BY cantidad DESC 
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error al obtener top tipos de membresía: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener últimas membresías creadas
 */
function getRecentMemberships($limit = 10) {
    global $pdo;
    
    try {
        $sql = "SELECT m.id, c.nombre, c.apellido_paterno, c.apellido_materno, 
                       tm.nombre as tipo_membresia, tm.precio, 
                       m.fecha_inicio, m.fecha_fin, m.created_at, m.metodo_pago
                FROM membresias m 
                JOIN clientes c ON m.cliente_id = c.id 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                ORDER BY m.created_at DESC 
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error al obtener últimas membresías: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener estadísticas por método de pago
 */
function getPaymentMethodStats() {
    global $pdo;
    
    try {
        $sql = "SELECT metodo_pago, COUNT(*) as cantidad, SUM(tm.precio) as ingresos_totales 
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                GROUP BY metodo_pago 
                ORDER BY cantidad DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error al obtener estadísticas por método de pago: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener estadísticas de crecimiento mensual
 */
function getMonthlyGrowthStats() {
    global $pdo;
    
    try {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as mes,
                    COUNT(*) as membresias,
                    SUM(tm.precio) as ingresos
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY mes DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error al obtener estadísticas de crecimiento mensual: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener membresías que expiran pronto
 */
function getExpiringMemberships($days = 7) {
    global $pdo;
    
    try {
        $sql = "SELECT m.id, c.nombre, c.apellido_paterno, c.apellido_materno,
                       tm.nombre as tipo_membresia, m.fecha_fin, m.created_at
                FROM membresias m 
                JOIN clientes c ON m.cliente_id = c.id 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                WHERE m.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY m.fecha_fin ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error al obtener membresías por expirar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener estadísticas de retención de clientes
 */
function getCustomerRetentionStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Clientes con múltiples membresías
        $sql = "SELECT COUNT(*) as clientes_recurrentes 
                FROM (
                    SELECT cliente_id, COUNT(*) as membresias_count
                    FROM membresias 
                    GROUP BY cliente_id 
                    HAVING COUNT(*) > 1
                ) as clientes_recurrentes";
        $stmt = $pdo->query($sql);
        $stats['clientes_recurrentes'] = $stmt->fetch()['clientes_recurrentes'];
        
        // Total de clientes únicos
        $sql = "SELECT COUNT(DISTINCT cliente_id) as total_clientes FROM membresias";
        $stmt = $pdo->query($sql);
        $stats['total_clientes'] = $stmt->fetch()['total_clientes'];
        
        // Calcular tasa de retención
        if ($stats['total_clientes'] > 0) {
            $stats['tasa_retencion'] = round(($stats['clientes_recurrentes'] / $stats['total_clientes']) * 100, 2);
        } else {
            $stats['tasa_retencion'] = 0;
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log('Error al obtener estadísticas de retención: ' . $e->getMessage());
        return false;
    }
}

/**
 * Formatear datos para gráficas
 */
function formatChartData($data, $days = 7) {
    $formatted_data = [];
    $labels = [];
    $values = [];
    
    // Crear array con todos los días
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('d/m', strtotime($date));
        $values[] = 0; // Valor por defecto
    }
    
    // Llenar con datos reales
    if ($data) {
        foreach ($data as $item) {
            $date_index = array_search(date('d/m', strtotime($item['fecha'])), $labels);
            if ($date_index !== false) {
                $values[$date_index] = $item['cantidad'] ?? $item['ingresos'] ?? 0;
            }
        }
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}

/**
 * Calcular porcentaje de cambio
 */
function calculatePercentageChange($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 2);
}

/**
 * Obtener estadísticas comparativas (hoy vs ayer)
 */
function getComparativeStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Membresías hoy vs ayer
        $sql = "SELECT 
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as hoy,
                    SUM(CASE WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as ayer
                FROM membresias";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch();
        $stats['membresias_hoy'] = $result['hoy'];
        $stats['membresias_ayer'] = $result['ayer'];
        $stats['cambio_membresias'] = calculatePercentageChange($result['hoy'], $result['ayer']);
        
        // Ingresos hoy vs ayer
        $sql = "SELECT 
                    SUM(CASE WHEN DATE(m.created_at) = CURDATE() THEN tm.precio ELSE 0 END) as hoy,
                    SUM(CASE WHEN DATE(m.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN tm.precio ELSE 0 END) as ayer
                FROM membresias m 
                JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch();
        $stats['ingresos_hoy'] = $result['hoy'];
        $stats['ingresos_ayer'] = $result['ayer'];
        $stats['cambio_ingresos'] = calculatePercentageChange($result['hoy'], $result['ayer']);
        
        return $stats;
    } catch (PDOException $e) {
        error_log('Error al obtener estadísticas comparativas: ' . $e->getMessage());
        return false;
    }
}
?> 