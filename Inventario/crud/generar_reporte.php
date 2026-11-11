<?php
require_once 'includes/db.php';
date_default_timezone_set('America/Mexico_City');
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once 'tcpdf/tcpdf.php';
require_once '../../log_activity.php';

class MYPDF_General extends TCPDF {
    private $empresa_nombre;

    public function setEmpresaNombre($nombre) {
        $this->empresa_nombre = $nombre;
    }

    public function Header() {
        $logoPath = dirname(__FILE__) . '/img/logolargo.png';
        if (file_exists($logoPath)) {
            // Logotipo en la parte superior izquierda
            $this->Image($logoPath, 10, 6, 40);
        }
        
        $this->SetY(8);
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(0, 51, 102); // Azul corporativo (#003366)
        $this->Cell(0, 8, 'REPORTE GENERAL DE EQUIPOS', 0, false, 'C');
        
        $this->SetY(16);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(102, 102, 102);
        $this->Cell(0, 5, 'EMPRESA: ' . $this->empresa_nombre, 0, false, 'C');

        // Línea divisora
        $this->SetDrawColor(0, 51, 102); // Color azul
        $this->SetLineWidth(0.5); // Grosor de línea
        $this->Line(10, 23, 285.4, 23); // x1,y1,x2,y2
    }

    public function Footer() {
        
        $this->SetY(-10);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, false, 'C');
        
        $this->SetY(-10);
        $this->Cell(0, 10, 'Fecha de generación: ' . date('d/m/Y'), 0, false, 'R');
    }
}

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reporte.php');
    exit;
}

$tipo_reporte = $_POST['tipo_reporte'] ?? 'general';

if ($tipo_reporte !== 'carta_mantenimiento' && (!isset($_POST['equipos']) || empty($_POST['equipos']))) {
    header('Location: reporte.php');
    exit;
}

$equipos_ids = $_POST['equipos'] ?? [];
$id_list = !empty($equipos_ids) ? implode(',', array_map('intval', $equipos_ids)) : '0';
$tipo_reporte = $_POST['tipo_reporte'] ?? 'general';
$empresa_nombre = $_POST['empresa_nombre'] ?? 'Todas las empresas';

// --- CONTADOR Y CSV INDEPENDIENTE PARA CADA TIPO DE REPORTE Y EMPRESA ---
function obtenerNumeroSecuencia($tipo, $empresa_nombre, $equipos_ids, $csv_file, $generarNumeroFn, $id_empresa_usuario = null) {
    $empresa_key = $id_empresa_usuario !== null ? $id_empresa_usuario : 'global';
    
    // 1. Clave basada en hash del contenido (estable y ligada al producto/equipos)
    $sorted_equipos = $equipos_ids;
    if (is_array($sorted_equipos)) {
        sort($sorted_equipos);
        $equipos_key = implode(',', $sorted_equipos);
    } else {
        $equipos_key = (string)$sorted_equipos;
    }
    $hash_key = md5($tipo . $equipos_key);
    
    // 2. Clave basada en report_id de formulario (sesión/refresh)
    $report_id_key = null;
    if (!empty($_POST['report_id'])) {
        $mante_cat = $_POST['mantenimiento_categoria'] ?? '';
        $report_id_key = $_POST['report_id'] . '_' . $tipo . '_' . $mante_cat;
    }
    
    $numero_reporte = null;
    $reporte_map = [];
    if (file_exists($csv_file)) {
        $lines = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $key = $parts[0];
                $num = $parts[1];
                $reporte_map[$key] = $num;
            }
        }
    }
    
    // Intentar buscar por report_id_key primero (compatibilidad/refresh directo)
    if ($report_id_key !== null && isset($reporte_map[$report_id_key])) {
        $numero_reporte = $reporte_map[$report_id_key];
    } 
    // Si no se encuentra, buscar por la clave de hash estable de los equipos
    elseif (isset($reporte_map[$hash_key])) {
        $numero_reporte = $reporte_map[$hash_key];
    } 
    // Si es completamente nuevo, generar número y asociarlo al hash estable
    else {
        $numero_reporte = $generarNumeroFn($id_empresa_usuario);
        $reporte_map[$hash_key] = $numero_reporte;
        
        $lines = [];
        foreach ($reporte_map as $key => $num) {
            $lines[] = $key . ',' . $num;
        }
        file_put_contents($csv_file, implode("\n", $lines));
    }
    return $numero_reporte;
}

//--

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

$query = "SELECT *, id_equipo, equipo_number, COALESCE(equipo_number, id_equipo) as display_id FROM equipos WHERE id_equipo IN ($id_list)";

// Agregar filtro por empresa del usuario para verificar permisos
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $query .= " AND id_empresa_usuario = " . intval($user_empresa_id);
}

$result = $conn->query($query);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

$equipos = [];
while ($row = $result->fetch_assoc()) {
    $equipos[] = $row;
}

switch ($tipo_reporte) {
    case 'carta_responsiva':
        // Crear archivo CSV específico por empresa si aplica y obtener folio estable
        $csv_file_responsiva = $user_empresa_id !== null ? 'reportes_generados_responsiva_empresa_' . $user_empresa_id . '.csv' : 'reportes_generados_responsiva.csv';
        $num_resp = obtenerNumeroSecuencia('carta_responsiva', $empresa_nombre, $equipos_ids, $csv_file_responsiva, 'generarNumeroReporte', $user_empresa_id);
        logActivity('Generación de reporte', 'equipos', null, 'Tipo: carta_responsiva, Empresa: ' . $empresa_nombre . ', Equipos: ' . implode(',', $equipos_ids));
        generarCartaResponsiva($equipos, $empresa_nombre, $num_resp);
        break;
    case 'carta_entrega':
        // Crear archivo CSV específico por empresa si aplica
        $csv_file_entrega = $user_empresa_id !== null ? 'reportes_generados_entrega_empresa_' . $user_empresa_id . '.csv' : 'reportes_generados_entrega.csv';
        $num_entrega = obtenerNumeroSecuencia('carta_entrega', $empresa_nombre, $equipos_ids, $csv_file_entrega, 'generarNumeroReporte2', $user_empresa_id);
        logActivity('Generación de reporte', 'equipos', null, 'Tipo: carta_entrega, Empresa: ' . $empresa_nombre . ', Equipos: ' . implode(',', $equipos_ids));
        generarCartaEntrega($equipos, $empresa_nombre, $num_entrega);
        break;
    case 'carta_prestamo':
        // Crear archivo CSV específico por empresa si aplica
        $csv_file_prestamo = $user_empresa_id !== null ? 'reportes_generados_prestamo_empresa_' . $user_empresa_id . '.csv' : 'reportes_generados_prestamo.csv';
        $num_prestamo = obtenerNumeroSecuencia('carta_prestamo', $empresa_nombre, $equipos_ids, $csv_file_prestamo, 'generarNumeroReporte3', $user_empresa_id);
        logActivity('Generación de reporte', 'equipos', null, 'Tipo: carta_prestamo, Empresa: ' . $empresa_nombre . ', Equipos: ' . implode(',', $equipos_ids));
        generarCartaPrestamo($equipos, $empresa_nombre, $num_prestamo);
        break;
    case 'prueba':
        logActivity('Generación de reporte', 'equipos', null, 'Tipo: prueba, Empresa: ' . $empresa_nombre . ', Equipos: ' . implode(',', $equipos_ids));
        generarPrueba($equipos, $empresa_nombre, $numero_reporte);
        break;
    case 'carta_mantenimiento':
        // Crear archivo CSV específico por empresa si aplica
        $csv_file_mantenimiento = $user_empresa_id !== null ? 'reportes_generados_mantenimiento_empresa_' . $user_empresa_id . '.csv' : 'reportes_generados_mantenimiento.csv';
        // Usar datos del formulario como identificador único para la secuencia si no hay equipos
        // Incluimos la categoría para que el contador pueda ser independiente si se desea, o mantenerlo global
        $manual_key = [$_POST['entrada_serie'] ?? 'manual', $_POST['entrada_cliente'] ?? 'cliente', $_POST['mantenimiento_categoria'] ?? 'Laptop'];
        $num_mantenimiento = obtenerNumeroSecuencia('carta_mantenimiento', $empresa_nombre, $manual_key, $csv_file_mantenimiento, 'generarNumeroReporteMantenimiento', $user_empresa_id);
        logActivity('Generación de reporte', 'equipos', null, 'Tipo: carta_mantenimiento (' . ($_POST['mantenimiento_categoria'] ?? 'Laptop') . '), Cliente: ' . ($_POST['entrada_cliente'] ?? 'Manual'));
        generarCartaMantenimiento($_POST, $num_mantenimiento);
        break;
    case 'general':
    default:
        generarReporteGeneral($equipos, $empresa_nombre);
}

function generarReporteGeneral($equipos, $empresa_nombre) {
    ob_start();
    include 'Reportes/plantilla_reporte.php';
    $html = ob_get_clean();

    $pdf = new MYPDF_General('L', 'mm', 'Carta', true, 'UTF-8', false);
    $pdf->setEmpresaNombre($empresa_nombre);

    $pdf->SetCreator('Sistema de Inventario');
    $pdf->SetAuthor('Secure IT');
    $pdf->SetTitle('Reporte de Equipos - ' . $empresa_nombre);
    $pdf->SetSubject('Reporte de Equipos');
    
    // Habilitar encabezados y pies de página
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    
    // Configurar márgenes para dar espacio al encabezado y pie de página
    $pdf->SetMargins(10, 28, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(15);
    
    // Salto de página automático
    $pdf->SetAutoPageBreak(true, 25);
    
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('reporte_equipos_' . $empresa_nombre . '.pdf', 'I');
}

function generarCartaResponsiva($equipos, $empresa_nombre, $numero_reporte) {
    global $numeroReporte;
    ob_start();
    $numeroReporte = $numero_reporte; 
    include 'Reportes/Plantilla_carta_responsiva.php';
    $html = ob_get_clean();

    $pdf = new TCPDF('P', 'mm', 'Carta', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema de Inventario');
    $pdf->SetAuthor('Secure IT');
    $pdf->SetTitle('Carta Responsiva - ' . $empresa_nombre);
    $pdf->SetSubject('Carta Responsiva');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 27, 15);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('carta_responsiva_' . $empresa_nombre . '.pdf', 'I');
}

function generarCartaMantenimiento($data, $numero_reporte) {
    global $numeroReporte;
    ob_start();
    $numeroReporte = $numero_reporte; 
    // Los datos ya están en $data (que es $_POST)
    include 'Reportes/Plantilla_carta_entrada.php';
    // Plantilla_carta_entrada.php ya maneja el Output y exit
}

function generarCartaEntrega($equipos, $empresa_nombre, $numero_reporte) {
    global $numeroReporte;
    ob_start();
    $numeroReporte = $numero_reporte; 
    include 'Reportes/Plantilla_carta_entrega.php';
    $html = ob_get_clean();

    $pdf = new TCPDF('P', 'mm', 'Carta', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema de Inventario');
    $pdf->SetAuthor('Secure IT');
    $pdf->SetTitle('Carta Entrega - ' . $empresa_nombre);
    $pdf->SetSubject('Carta Entrega');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 27, 15);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('carta_entrega_' . $empresa_nombre . '.pdf', 'I');
}

function generarCartaPrestamo($equipos, $empresa_nombre, $numero_reporte) {
    global $numeroReporte;
    ob_start();
    $numeroReporte = $numero_reporte; 
    include 'Reportes/Plantilla_carta_prestamo.php';
    $html = ob_get_clean();

    $pdf = new TCPDF('P', 'mm', 'Carta', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema de Inventario');
    $pdf->SetAuthor('Secure IT');
    $pdf->SetTitle('Carta Prestamo - ' . $empresa_nombre);
    $pdf->SetSubject('Carta Prestamo');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 27, 15);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('carta_prestamo_' . $empresa_nombre . '.pdf', 'I');
}

function generarPrueba($equipos, $empresa_nombre, $numero_reporte) {
    ob_start();
    include 'Reportes/Prueba.php';
}

function generarNumeroReporte($id_empresa_usuario = null) {
    $empresa_suffix = $id_empresa_usuario !== null ? '_empresa_' . $id_empresa_usuario : '';
    $archivo = 'contador_reporte_responsiva' . $empresa_suffix . '.txt';
    
    if (!file_exists($archivo)) file_put_contents($archivo, '0');

    $numero = (int)file_get_contents($archivo);
    $numero++;
    file_put_contents($archivo, $numero);
    
    $seq = str_pad($numero, 3, '0', STR_PAD_LEFT);
    return "CR-{$seq}";
}

function generarNumeroReporte2($id_empresa_usuario = null) {
    $empresa_suffix = $id_empresa_usuario !== null ? '_empresa_' . $id_empresa_usuario : '';
    $archivo = 'contador_reporte_entrega' . $empresa_suffix . '.txt';
    
    if (!file_exists($archivo)) file_put_contents($archivo, '0');

    $numero = (int)file_get_contents($archivo);
    $numero++;
    file_put_contents($archivo, $numero);
    
    $seq = str_pad($numero, 3, '0', STR_PAD_LEFT);
    return "CE-{$seq}";
}

function generarNumeroReporte3($id_empresa_usuario = null) {
    $empresa_suffix = $id_empresa_usuario !== null ? '_empresa_' . $id_empresa_usuario : '';
    $archivo = 'contador_reporte_prestamo' . $empresa_suffix . '.txt';
    
    if (!file_exists($archivo)) file_put_contents($archivo, '0');

    $numero = (int)file_get_contents($archivo);
    $numero++;
    file_put_contents($archivo, $numero);
    
    $seq = str_pad($numero, 3, '0', STR_PAD_LEFT);
    return "CP-{$seq}";
}

function generarNumeroReporteMantenimiento($id_empresa_usuario = null) {
    // Determinar prefijo y nombre de archivo según categoría
    $categoria = $_POST['mantenimiento_categoria'] ?? 'Laptop';
    $prefix = 'CM';
    $cat_file = 'mantenimiento';
    
    if ($categoria === 'Laptop') {
        $prefix = 'CML';
        $cat_file = 'mantenimiento_laptop';
    } elseif ($categoria === 'PC') {
        $prefix = 'CMPC';
        $cat_file = 'mantenimiento_pc';
    } elseif ($categoria === 'Impresora') {
        $prefix = 'CMI';
        $cat_file = 'mantenimiento_impresora';
    } elseif ($categoria === 'Mantenimiento Preventivo') {
        $prefix = 'CMP';
        $cat_file = 'mantenimiento_preventivo';
    } elseif ($categoria === 'Salida a Laboratorio') {
        $prefix = 'CSL';
        $cat_file = 'mantenimiento_salida_lab';
    }

    $empresa_suffix = $id_empresa_usuario !== null ? '_empresa_' . $id_empresa_usuario : '';
    $archivo = "contador_{$cat_file}" . $empresa_suffix . ".txt";
    
    if (!file_exists($archivo)) file_put_contents($archivo, '0');

    $numero = (int)file_get_contents($archivo);
    $numero++;
    file_put_contents($archivo, $numero);
    
    $seq = str_pad($numero, 3, '0', STR_PAD_LEFT);
    return "{$prefix}-{$seq}";
}
?>