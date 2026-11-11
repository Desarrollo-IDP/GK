<?php

/**
 * Configuración específica para el dashboard
 * Este archivo contiene configuraciones y constantes para el dashboard
 */

// Configuración de gráficas
define('CHART_COLORS', [
    'primary' => 'rgba(102, 126, 234, 0.8)',
    'success' => 'rgba(40, 167, 69, 0.8)',
    'danger' => 'rgba(220, 53, 69, 0.8)',
    'warning' => 'rgba(255, 193, 7, 0.8)',
    'info' => 'rgba(23, 162, 184, 0.8)',
    'purple' => 'rgba(111, 66, 193, 0.8)',
    'orange' => 'rgba(253, 126, 20, 0.8)',
    'gray' => 'rgba(108, 117, 125, 0.8)'
]);

// Configuración de períodos de tiempo
define('DEFAULT_CHART_DAYS', 7);
define('DEFAULT_REPORT_DAYS', 30);
define('MAX_CHART_DAYS', 365);

// Configuración de límites
define('MAX_RECENT_MEMBERSHIPS', 10);
define('MAX_TOP_CLIENTS', 10);
define('MAX_TOP_TYPES', 5);

// Configuración de alertas
define('EXPIRING_WARNING_DAYS', 7);
define('LOW_REVENUE_THRESHOLD', 1000); // $1000 MXN
define('HIGH_REVENUE_THRESHOLD', 10000); // $10000 MXN

// Configuración de exportación
define('EXPORT_ENABLED', true);
define('PDF_EXPORT_ENABLED', true);
define('EXCEL_EXPORT_ENABLED', true);

// Configuración de cache
define('DASHBOARD_CACHE_ENABLED', true);
define('DASHBOARD_CACHE_DURATION', 300); // 5 minutos

/**
 * Obtener colores para gráficas
 */
function getChartColors($count) {
    $colors = CHART_COLORS;
    $result = [];
    
    for ($i = 0; $i < $count; $i++) {
        $result[] = array_values($colors)[$i % count($colors)];
    }
    
    return $result;
}

/**
 * Formatear moneda
 */
function formatCurrency($amount, $currency = 'MXN') {
    return '$' . number_format($amount, 2);
}

/**
 * Formatear porcentaje
 */
function formatPercentage($value, $total) {
    if ($total == 0) return '0%';
    return round(($value / $total) * 100, 1) . '%';
}

/**
 * Obtener estado de membresía
 */
function getMembershipStatus($fecha_inicio, $fecha_fin) {
    $hoy = date('Y-m-d');
    
    if ($fecha_fin < $hoy) {
        return 'expirada';
    } elseif ($fecha_inicio > $hoy) {
        return 'pendiente';
    } else {
        return 'activa';
    }
}

/**
 * Obtener clase CSS para estado
 */
function getStatusClass($status) {
    switch ($status) {
        case 'activa':
            return 'status-active';
        case 'expirada':
            return 'status-expired';
        case 'pendiente':
            return 'status-pending';
        default:
            return 'status-unknown';
    }
}

/**
 * Obtener icono para método de pago
 */
function getPaymentMethodIcon($method) {
    switch ($method) {

        case 'cash':
            return 'fas fa-money-bill-wave';
        case 'transfer':
            return 'fas fa-university';
        default:
            return 'fas fa-credit-card';
    }
}

/**
 * Obtener clase CSS para método de pago
 */
function getPaymentMethodClass($method) {
    switch ($method) {

        case 'cash':
            return 'badge-cash';
        case 'transfer':
            return 'badge-transfer';
        default:
            return 'badge-secondary';
    }
}

/**
 * Calcular tendencia
 */
function calculateTrend($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

/**
 * Obtener clase CSS para tendencia
 */
function getTrendClass($trend) {
    if ($trend > 0) {
        return 'text-success';
    } elseif ($trend < 0) {
        return 'text-danger';
    } else {
        return 'text-muted';
    }
}

/**
 * Obtener icono para tendencia
 */
function getTrendIcon($trend) {
    if ($trend > 0) {
        return 'fas fa-arrow-up';
    } elseif ($trend < 0) {
        return 'fas fa-arrow-down';
    } else {
        return 'fas fa-minus';
    }
}

/**
 * Formatear fecha para mostrar
 */
function formatDisplayDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Formatear fecha para gráficas
 */
function formatChartDate($date) {
    return date('d/m', strtotime($date));
}

/**
 * Obtener período de tiempo para filtros
 */
function getTimePeriods() {
    return [
        'today' => 'Hoy',
        'yesterday' => 'Ayer',
        'last_7_days' => 'Últimos 7 días',
        'last_30_days' => 'Últimos 30 días',
        'this_month' => 'Este mes',
        'last_month' => 'Mes pasado',
        'this_year' => 'Este año',
        'custom' => 'Personalizado'
    ];
}

/**
 * Obtener fechas para período
 */
function getPeriodDates($period) {
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            return [$today, $today];
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return [$yesterday, $yesterday];
        case 'last_7_days':
            $start = date('Y-m-d', strtotime('-6 days'));
            return [$start, $today];
        case 'last_30_days':
            $start = date('Y-m-d', strtotime('-29 days'));
            return [$start, $today];
        case 'this_month':
            $start = date('Y-m-01');
            return [$start, $today];
        case 'last_month':
            $start = date('Y-m-01', strtotime('-1 month'));
            $end = date('Y-m-t', strtotime('-1 month'));
            return [$start, $end];
        case 'this_year':
            $start = date('Y-01-01');
            return [$start, $today];
        default:
            return [date('Y-m-01'), $today];
    }
}

/**
 * Validar parámetros de filtro
 */
function validateFilterParams($params) {
    $errors = [];
    
    if (!empty($params['fecha_inicio']) && !empty($params['fecha_fin'])) {
        if (strtotime($params['fecha_inicio']) > strtotime($params['fecha_fin'])) {
            $errors[] = 'La fecha de inicio no puede ser posterior a la fecha de fin';
        }
    }
    
    if (!empty($params['fecha_inicio'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['fecha_inicio'])) {
            $errors[] = 'Formato de fecha de inicio inválido';
        }
    }
    
    if (!empty($params['fecha_fin'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['fecha_fin'])) {
            $errors[] = 'Formato de fecha de fin inválido';
        }
    }
    
    return $errors;
}

/**
 * Obtener configuración de gráfica
 */
function getChartConfig($type = 'line') {
    $config = [
        'responsive' => true,
        'maintainAspectRatio' => false,
        'plugins' => [
            'legend' => [
                'display' => false
            ]
        ]
    ];
    
    if ($type === 'line') {
        $config['scales'] = [
            'y' => [
                'beginAtZero' => true,
                'ticks' => [
                    'callback' => 'function(value) { return "$" + value.toLocaleString(); }'
                ]
            ]
        ];
    } elseif ($type === 'bar') {
        $config['scales'] = [
            'y' => [
                'beginAtZero' => true,
                'ticks' => [
                    'stepSize' => 1
                ]
            ]
        ];
    } elseif ($type === 'doughnut') {
        $config['plugins']['legend'] = [
            'position' => 'bottom'
        ];
    }
    
    return $config;
}

/**
 * Generar ID único para gráficas
 */
function generateChartId($prefix = 'chart') {
    return $prefix . '_' . uniqid();
}

/**
 * Obtener datos de ejemplo para gráficas (cuando no hay datos reales)
 */
function getSampleChartData($days = 7) {
    $labels = [];
    $data = [];
    
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = formatChartDate($date);
        $data[] = rand(0, 10); // Datos de ejemplo
    }
    
    return [
        'labels' => $labels,
        'data' => $data
    ];
}
?> 