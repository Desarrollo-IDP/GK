<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../log_activity.php';
checkAuth();

// Configurar TCPDF con el mismo encabezado y pie que Plantilla_carta_entrega.php
ob_start();

if (!class_exists('TCPDF')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);

    // Ruta base del proyecto (GK)
    // __FILE__ = .../almacen/modules/reportes/reportes_pdf.php
    // Subimos 4 niveles para llegar a la raíz del proyecto
    $projectRoot = dirname(dirname(dirname(dirname(__FILE__))));

    // En este proyecto la librería TCPDF está dentro de Inventario/crud/tcpdf
    if (!defined('K_PATH_MAIN')) {
        define('K_PATH_MAIN', $projectRoot . '/Inventario/crud/tcpdf/');
    }

    $tcpdf_path = $projectRoot . '/Inventario/crud/tcpdf/tcpdf.php';

    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
    } else {
        die("TCPDF library not found at: " . $tcpdf_path);
    }
}

class MYPDF_REPORTES extends TCPDF
{
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 10);

        // Mismas rutas de imágenes que en Plantilla_carta_entrega.php,
        // pero desde la raíz del proyecto
        $projectRoot = dirname(dirname(dirname(dirname(__FILE__))));
        $imagePath = $projectRoot . '/Inventario/crud/img/Encabezado.png';

        if (file_exists($imagePath)) {
            $this->Image($imagePath, 0.5, $this->GetY(), 215);
        }

        $this->SetY(35);
        $this->Cell(0, 0, '', 0, 'C');
    }

    public function Footer()
    {
        $this->SetY(-22);

        // Mismas rutas de imágenes que en Plantilla_carta_entrega.php,
        // pero desde la raíz del proyecto
        $projectRoot = dirname(dirname(dirname(dirname(__FILE__))));
        $imagePath = $projectRoot . '/Inventario/crud/img/Pie de página.png';

        if (file_exists($imagePath)) {
            $this->Image($imagePath, 0.5, $this->GetY(), 215);
        }

        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 0, '', 0, 'C');
    }
}

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Reutilizar las mismas consultas de reportes.php para que el PDF muestre el mismo resumen
$has_historial_column = false;
$has_productos_column = false;
$has_almacenes_column = false;
$has_cartas_column = false;

$check_historial = $conn->query("SHOW COLUMNS FROM historial_movimientos LIKE 'id_empresa'");
if ($check_historial && $check_historial->num_rows > 0) {
    $has_historial_column = true;
}

$check_productos = $conn->query("SHOW COLUMNS FROM productos_almacen LIKE 'id_empresa'");
if ($check_productos && $check_productos->num_rows > 0) {
    $has_productos_column = true;
}

$check_almacenes = $conn->query("SHOW COLUMNS FROM almacenes LIKE 'id_empresa'");
if ($check_almacenes && $check_almacenes->num_rows > 0) {
    $has_almacenes_column = true;
}

$check_cartas = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
if ($check_cartas && $check_cartas->num_rows > 0) {
    $has_cartas_column = true;
}

// Estadísticas de movimientos
$stats_movimientos = [];
$movimientos_query = "SELECT tipo_movimiento, COUNT(*) as total FROM historial_movimientos";
if ($has_historial_column && $user_empresa_id !== null) {
    $movimientos_query .= " WHERE id_empresa = " . intval($user_empresa_id);
}
$movimientos_query .= " GROUP BY tipo_movimiento";
$result = $conn->query($movimientos_query);
while ($row = $result->fetch_assoc()) {
    $stats_movimientos[$row['tipo_movimiento']] = $row['total'];
}

// Productos más movidos
$productos_movidos_query = "
    SELECT p.nombre_producto, p.codigo_producto, COUNT(*) as total_movimientos
    FROM historial_movimientos hm
    LEFT JOIN productos_almacen p ON hm.id_producto = p.id_producto
    WHERE p.id_producto IS NOT NULL";
if ($has_historial_column && $user_empresa_id !== null) {
    $productos_movidos_query .= " AND hm.id_empresa = " . intval($user_empresa_id);
}
$productos_movidos_query .= "
    GROUP BY p.id_producto
    ORDER BY total_movimientos DESC
    LIMIT 10";
$productos_movidos = $conn->query($productos_movidos_query);

// Productos con stock bajo
$stock_bajo_query = "
    SELECT p.*, a.nombre as almacen_nombre
    FROM productos_almacen p
    LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen
    WHERE p.cantidad <= p.stock_minimo AND p.stock_minimo > 0";
if ($has_productos_column && $user_empresa_id !== null) {
    $stock_bajo_query .= " AND p.id_empresa = " . intval($user_empresa_id);
}
$stock_bajo_query .= " ORDER BY p.cantidad ASC";
$stock_bajo = $conn->query($stock_bajo_query);

// Valor total por almacén
$valor_almacenes_query = "
    SELECT a.nombre, a.codigo, SUM(p.valor_total) as valor_total
    FROM almacenes a
    LEFT JOIN productos_almacen p ON a.id_almacen = p.id_almacen";
if ($has_almacenes_column && $user_empresa_id !== null) {
    $valor_almacenes_query .= " WHERE a.id_empresa = " . intval($user_empresa_id);
}
if ($has_productos_column && $user_empresa_id !== null) {
    $valor_almacenes_query .= ($has_almacenes_column && $user_empresa_id !== null ? " AND" : " WHERE") . " p.id_empresa = " . intval($user_empresa_id);
}
$valor_almacenes_query .= "
    GROUP BY a.id_almacen
    ORDER BY valor_total DESC";
$valor_almacenes = $conn->query($valor_almacenes_query);

// Cartas de entrega de los últimos 30 días (fecha calendario, zona horaria del almacén)
$cartas_mes_query = "
    SELECT COUNT(DISTINCT numero_carta) as total
    FROM cartas_entrega
    WHERE fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      AND fecha_entrega <= CURDATE()";
if ($has_cartas_column && $user_empresa_id !== null) {
    $cartas_mes_query .= " AND id_empresa = " . intval($user_empresa_id);
}
$cartas_mes = $conn->query($cartas_mes_query)->fetch_assoc()['total'];

// Crear PDF en orientación vertical (portrait)
$pdf = new MYPDF_REPORTES('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('Sistema de Almacén');
$pdf->SetAuthor('Secure IT');
$pdf->SetTitle('Reporte general de almacén');
$pdf->SetSubject('Reporte de movimientos y stock');

$pdf->SetMargins(10, 27, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 20);

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);
$pdf->SetY(40);

// Fecha
$fecha = fechaHoraActualAlmacen();

$html = '
<style>
    h1 {
        font-size: 14px;
        font-weight: bold;
        text-align: left;
        margin-bottom: 4px;
    }
    h2 {
        font-size: 11px;
        font-weight: bold;
        margin-top: 10px;
        margin-bottom: 4px;
    }
    .fecha {
        font-size: 9px;
        color: #4b5563;
        text-align: right;
        margin-bottom: 6px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8px;
        margin-bottom: 6px;
    }
    th {
        background-color: #111827;
        color: #ffffff;
        padding: 4px 3px;
        text-align: left;
    }
    td {
        padding: 3px;
        border-bottom: 1px solid #e5e7eb;
    }
    tr:nth-child(even) td {
        background-color: #f9fafb;
    }
    .small {
        font-size: 8px;
    }
</style>

<div class="fecha">Fecha de generación: ' . htmlspecialchars($fecha) . '</div>
<h1>Reporte general de almacén</h1>

<h2>Movimientos por tipo</h2>
<table>
    <thead>
        <tr>
            <th>Tipo de movimiento</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>';

$tipos_movimiento = ['Entrada', 'Salida', 'Transferencia', 'Ajuste', 'Entrega', 'Devolución'];
foreach ($tipos_movimiento as $tipo) {
    $total = $stats_movimientos[$tipo] ?? 0;
    $html .= '
        <tr>
            <td>' . htmlspecialchars($tipo) . '</td>
            <td>' . (int)$total . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<h2>Productos más movidos (Top 10)</h2>
<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Producto</th>
            <th>Total movimientos</th>
        </tr>
    </thead>
    <tbody>';

if ($productos_movidos && $productos_movidos->num_rows > 0) {
    while ($prod = $productos_movidos->fetch_assoc()) {
        $html .= '
        <tr>
            <td>' . htmlspecialchars($prod['codigo_producto']) . '</td>
            <td>' . htmlspecialchars($prod['nombre_producto']) . '</td>
            <td>' . (int)$prod['total_movimientos'] . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr><td colspan="3" class="small">Sin datos</td></tr>';
}

$html .= '
    </tbody>
</table>

<h2>Valor total por almacén</h2>
<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Almacén</th>
            <th>Valor total</th>
        </tr>
    </thead>
    <tbody>';

if ($valor_almacenes && $valor_almacenes->num_rows > 0) {
    while ($alm = $valor_almacenes->fetch_assoc()) {
        $valor = $alm['valor_total'] !== null ? number_format($alm['valor_total'], 2) : '0.00';
        $html .= '
        <tr>
            <td>' . htmlspecialchars($alm['codigo']) . '</td>
            <td>' . htmlspecialchars($alm['nombre']) . '</td>
            <td>$' . $valor . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr><td colspan="3" class="small">Sin datos</td></tr>';
}

$html .= '
    </tbody>
</table>

<h2>Productos con stock bajo</h2>
<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Producto</th>
            <th>Almacén</th>
            <th>Cantidad</th>
            <th>Stock mínimo</th>
        </tr>
    </thead>
    <tbody>';

if ($stock_bajo && $stock_bajo->num_rows > 0) {
    while ($prod = $stock_bajo->fetch_assoc()) {
        $html .= '
        <tr>
            <td>' . htmlspecialchars($prod['codigo_producto']) . '</td>
            <td>' . htmlspecialchars($prod['nombre_producto']) . '</td>
            <td>' . htmlspecialchars($prod['almacen_nombre']) . '</td>
            <td>' . (int)$prod['cantidad'] . '</td>
            <td>' . (int)$prod['stock_minimo'] . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr><td colspan="5" class="small">Sin productos con stock bajo</td></tr>';
}

$html .= '
    </tbody>
</table>

<h2>Cartas de entrega</h2>
<table>
    <thead>
        <tr>
            <th>Cartas con fecha de entrega en los últimos 30 días (hasta hoy)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>' . (int)$cartas_mes . '</td>
        </tr>
    </tbody>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');

if (ob_get_length()) {
    ob_end_clean();
}

logActivity('Generación de reporte PDF', 'almacen_reportes', null, 'Reporte general de almacén descargado');

$pdf->Output('reporte_general_almacen.pdf', 'I');
exit;
