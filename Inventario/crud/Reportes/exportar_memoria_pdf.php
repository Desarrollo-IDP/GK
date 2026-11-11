<?php
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../includes/db.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/includes/empresa_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/log_activity.php';
checkAuth();

// Verificar TCPDF
if (!class_exists('TCPDF')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    if (!defined('K_PATH_MAIN')) {
        define('K_PATH_MAIN', __DIR__ . '/tcpdf/');
    }
    $tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
    } else {
        die("Error: Librería TCPDF no encontrada.");
    }
}

// Extensión personalizada de TCPDF para Encabezado y Pie de página oficiales
class MYPDF_MEMORIA extends TCPDF {
    public $empresaNombre = '';
    public $sedeNombre = '';

    public function Header() {
        $headerImg = dirname(__DIR__) . '/img/Encabezado.png';
        if (!file_exists($headerImg)) {
            $headerImg = __DIR__ . '/Encabezado.png';
        }
        
        if (file_exists($headerImg)) {
            $this->Image($headerImg, 0, 0, 216, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        $this->SetY(28);
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(30, 41, 59); // #1e293b
        $this->Cell(0, 8, 'REPORTE DE MEMORIA TÉCNICA', 0, 1, 'R');
        
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(37, 99, 235); // #2563eb
        $subt = mb_strtoupper($this->empresaNombre, 'UTF-8');
        $this->Cell(0, 5, $subt, 0, 1, 'R');
        $this->SetY(42);
    }

    public function Footer() {
        $this->SetY(-22);
        $footerImg = dirname(__DIR__) . '/img/Pie de página.png';
        if (!file_exists($footerImg)) {
            $footerImg = __DIR__ . '/Pie de página.png';
        }

        if (file_exists($footerImg)) {
            $this->Image($footerImg, 0, $this->GetY(), 216, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, false, 'C');

    }
}

// Parámetros de entrada
$id_empresa = isset($_GET['id_empresa']) ? intval($_GET['id_empresa']) : 0;
$sede_filtro = isset($_GET['sede']) ? trim($_GET['sede']) : '';

if ($id_empresa <= 0) {
    die("ID de empresa no válido.");
}

// Validar empresa asociada al usuario si aplica
$user_empresa_id = getCurrentUserEmpresaId();
if ($user_empresa_id !== null && intval($user_empresa_id) !== $id_empresa && !isAdmin()) {
    die("No tiene permisos para consultar esta empresa.");
}

// 1. Obtener datos de la empresa
$stmt_emp = $conn->prepare("SELECT id_empresa, nombre FROM empresas_equipos WHERE id_empresa = ?");
$stmt_emp->bind_param('i', $id_empresa);
$stmt_emp->execute();
$res_emp = $stmt_emp->get_result();
$empresa = $res_emp->fetch_assoc();

if (!$empresa) {
    die("Empresa no encontrada.");
}

$nombre_empresa = $empresa['nombre'];

// 2. Obtener Sedes disponibles
$sql_sedes = "SELECT DISTINCT sede FROM empresa_memoria_tecnica WHERE id_empresa = ? AND activo = 1 AND TRIM(sede) != '' ORDER BY sede ASC";
$stmt_s = $conn->prepare($sql_sedes);
$stmt_s->bind_param('i', $id_empresa);
$stmt_s->execute();
$res_s = $stmt_s->get_result();
$sedes_db = [];
while ($row = $res_s->fetch_assoc()) {
    $sedes_db[] = $row['sede'];
}

// Determinar qué sedes incluir
$sedes_a_procesar = [];
if (!empty($sede_filtro) && strtolower($sede_filtro) !== 'todas' && strtolower($sede_filtro) !== 'all') {
    $sedes_a_procesar[] = $sede_filtro;
} else {
    $sedes_a_procesar = !empty($sedes_db) ? $sedes_db : ['General'];
}

// 3. Crear instancia del PDF
$pdf = new MYPDF_MEMORIA('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->empresaNombre = $nombre_empresa;
$pdf->sedeNombre = (count($sedes_a_procesar) === 1) ? $sedes_a_procesar[0] : 'Todas las Sedes';

$pdf->SetCreator('Sistema de Inventario SECUÍTI');
$pdf->SetAuthor('SECUÍTI');
$pdf->SetTitle('Memoria Técnica - ' . $nombre_empresa);
$pdf->SetSubject('Memoria Técnica y Diagrama de Red');

// Margenes (Izquierdo, Arriba, Derecho)
$pdf->SetMargins(12, 45, 12);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);
$pdf->SetAutoPageBreak(TRUE, 34);

// Agregar primera página
$pdf->AddPage();

// Archivos temporales para limpiar al finalizar
$tempFilesToDelete = [];

// Estilos CSS comunes para el HTML del PDF
$css = '
<style>
    .section-title {
        font-size: 11pt;
        font-weight: bold;
        color: #ffffff;
        background-color: #1e293b;
        padding: 8px 12px;
        margin-top: 18px;
        margin-bottom: 12px;
        border-radius: 4px;
    }
    .sub-title {
        font-size: 10pt;
        font-weight: bold;
        color: #1e3a8a;
        border-bottom: 1px solid #93c5fd;
        padding-bottom: 4px;
        margin-top: 14px;
        margin-bottom: 10px;
    }
    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        margin: 0;
        padding: 0;
    }
    .info-table td {
        padding: 10px 12px;
        font-size: 8.5pt;
        border: 0.5px solid #cbd5e1;
    }
    .info-label {
        background-color: #f1f5f9;
        font-weight: bold;
        color: #334155;
        width: 22%;
    }
    .note-card {
        width: 100%;
        max-width: 100%;
        border: 0.5px solid #a3b1c7;
        margin: 0 0 14px 0;
        page-break-inside: avoid;
        padding-left: 10px;
        padding-right: 10px;
        text-align: center;
        box-sizing: border-box;
        min-height: 150px;
        height: auto;
        display: table;
    }
    .note-card table {
        width: 100%;
        margin: 0;
        height: auto;
        display: table;
    }
    .note-card td {
        vertical-align: top;
    }
    .note-header {
        background-color: #a3b1c7;
        border-bottom: 0.5px solid #e2e8f0;
        padding: 10px 14px;
        text-align: center;
    }
    .note-title {
        font-size: 10pt;
        font-weight: bold;
        color: #1e3a8a;
    }
    .note-ref {
        font-size: 7.5pt;
        color: #64748b;
        font-family: "Courier", monospace;
        font-weight: bold;
        margin-right: 8px;
    }
    .note-meta {
        font-size: 7pt;
        color: #94a3b8;
    }
    .note-body {
        font-size: 8.5pt;
        color: #334155;
        line-height: 1.7;
        padding: 16px 16px 14px 16px;
        text-align: center;
    }
    .note-footer {
        background-color: #f8fafc;
        border-top: 0.5px solid #e2e8f0;
        padding: 8px 14px;
        font-size: 7.5pt;
        color: #94a3b8;
        text-align: center;
    }
    .badge-fijado {
        color: #6b7280;
        font-size: 6.5pt;
        font-weight: bold;
        border: 0.5px solid #d1d5db;
        background-color: #f3f4f6;
        padding: 1px 6px;
        letter-spacing: 0.5px;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        margin-bottom: 12px;
    }
    .data-table th {
        background-color: #0f172a;
        color: #ffffff;
        font-weight: bold;
        font-size: 8pt;
        padding: 6px 4px;
        text-align: center;
        border: 0.5px solid #020617;
    }
    .data-table td {
        font-size: 7.5pt;
        padding: 5px 4px;
        border: 0.5px solid #cbd5e1;
        color: #334155;
    }
    .row-even {
        background-color: #f8fafc;
    }

</style>
';

// Obtener el nombre del usuario autenticado
$usuario_logueado = '';
if (isset($_SESSION['user'])) {
    if (!empty($_SESSION['user']['nombre_usuario'])) {
        $usuario_logueado = $_SESSION['user']['nombre_usuario'];
    } elseif (!empty($_SESSION['user']['username'])) {
        $usuario_logueado = $_SESSION['user']['username'];
    } elseif (!empty($_SESSION['user']['nombre'])) {
        $usuario_logueado = $_SESSION['user']['nombre'];
    }
}

if (empty($usuario_logueado)) {
    $user_id_sess = $_SESSION['user']['id'] ?? $_SESSION['user']['id_usuario'] ?? 0;
    if ($user_id_sess > 0) {
        $stmt_u = $conn->prepare("SELECT nombre_usuario, username, nombre FROM usuarios WHERE id = ? OR id_usuario = ? LIMIT 1");
        $stmt_u->bind_param('ii', $user_id_sess, $user_id_sess);
        $stmt_u->execute();
        $res_u = $stmt_u->get_result();
        if ($row_u = $res_u->fetch_assoc()) {
            $usuario_logueado = !empty($row_u['nombre_usuario']) ? $row_u['nombre_usuario'] : (!empty($row_u['username']) ? $row_u['username'] : $row_u['nombre']);
        }
    }
}

if (empty($usuario_logueado)) {
    $usuario_logueado = 'Usuario Autenticado';
}

// Bloque 1: Resumen General
$html = $css;
$html .= '<div class="page-content">';
$html .= '
<table class="info-table" style="width: 100%;">
    <tr>
        <td class="info-label" width="22%">Empresa / Cliente:</td>
        <td width="28%" style="padding-left:10px;"><strong>' . htmlspecialchars($nombre_empresa) . '</strong></td>
        <td class="info-label" width="22%">Fecha de Generación:</td>
        <td width="28%">' . date('d/m/Y H:i:s') . '</td>
    </tr>
    <tr>
        <td class="info-label" width="22%">Sede(s) Exportada(s):</td>
        <td width="28%" style="padding-left:10px;">' . htmlspecialchars(implode(', ', $sedes_a_procesar)) . '</td>
        <td class="info-label" width="22%">Generado Por:</td>
        <td width="28%">' . htmlspecialchars($usuario_logueado) . '</td>
    </tr>
</table>
<br>';
$html .= '</div>';

$pdf->writeHTML($html, true, false, true, false, '');

// Procesar cada sede seleccionada
foreach ($sedes_a_procesar as $idxSede => $nombreSede) {

    // Cada sede comienza en una página nueva
    if ($idxSede > 0) {
        $pdf->AddPage();
    }

    // 1. Obtener Diagrama de Red de esta sede
    $stmt_diag = $conn->prepare("SELECT id, archivo_url, mime_type, archivo_url, fecha_actualizacion FROM empresa_memoria_tecnica WHERE id_empresa = ? AND TRIM(sede) = TRIM(?) AND tipo = 'diagrama' AND activo = 1 ORDER BY fecha_actualizacion DESC LIMIT 1");
    $stmt_diag->bind_param('is', $id_empresa, $nombreSede);
    $stmt_diag->execute();
    $res_diag = $stmt_diag->get_result();
    $diagrama = $res_diag->fetch_assoc();

    // 2. Obtener Notas Técnicas de esta sede
    $stmt_notas = $conn->prepare("SELECT m.id, m.titulo, m.descripcion, m.fecha_actualizacion, m.fijado, u.nombre_usuario as usuario_nombre 
                                  FROM empresa_memoria_tecnica m 
                                  LEFT JOIN usuarios u ON m.actualizado_por = u.id_usuario 
                                  WHERE m.id_empresa = ? AND TRIM(m.sede) = TRIM(?) AND m.tipo = 'nota' AND m.activo = 1 
                                  ORDER BY m.fijado DESC, m.orden DESC, m.fecha_actualizacion DESC");
    $stmt_notas->bind_param('is', $id_empresa, $nombreSede);
    $stmt_notas->execute();
    $res_notas = $stmt_notas->get_result();
    $notas = [];
    while ($r_nota = $res_notas->fetch_assoc()) {
        $notas[] = $r_nota;
    }

    // Encabezado de la Sede
    $htmlSede = $css;
    $htmlSede .= '<div class="page-content">';
    $htmlSede .= '<div class="section-title">SEDE: ' . mb_strtoupper(htmlspecialchars($nombreSede), 'UTF-8') . '</div>';

    // Sección A: Diagrama de Red
    $htmlSede .= '<div class="sub-title">1. Diagrama de Red</div>';
    $htmlSede .= '</div>';

    $pdf->writeHTML($htmlSede, true, false, true, false, '');

    $imgRendered = false;

    if ($diagrama) {
        $imgPath = '';

        // Prioridad 1: Archivo físico
        if (!empty($diagrama['archivo_url'])) {
            $possiblePath = dirname(__DIR__) . '/' . $diagrama['archivo_url'];
            if (file_exists($possiblePath)) {
                $imgPath = $possiblePath;
            }
        }

        // Prioridad 2: Archivo binario en DB
        if (empty($imgPath) && !empty($diagrama['archivo_url'])) {
            $tmpFile = sys_get_temp_dir() . '/diag_' . $diagrama['id'] . '_' . time() . '.png';
            file_put_contents($tmpFile, $diagrama['archivo_url']);
            if (file_exists($tmpFile)) {
                $imgPath = $tmpFile;
                $tempFilesToDelete[] = $tmpFile;
            }
        }

        if (!empty($imgPath)) {
            // Verificar dimensiones de la imagen para ajustarla proporcionalmente en su propia hoja
            list($origW, $origH) = @getimagesize($imgPath);
            if ($origW > 0 && $origH > 0) {
                $maxW = 185; // mm
                $maxH = 140; // mm

                $ratio = min($maxW / $origW, $maxH / $origH);
                $finalW = round($origW * $ratio, 2);
                $finalH = round($origH * $ratio, 2);

                $currentY = $pdf->GetY();

                $pdf->Image($imgPath, (216 - $finalW) / 2, $currentY + 2, $finalW, $finalH, '', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
                $pdf->SetY($currentY + $finalH + 5);

                $infoDiag = '<div style="font-size: 8pt; color: #64748b; text-align: center;">Última actualización del diagrama: ' . date('d/m/Y H:i', strtotime($diagrama['fecha_actualizacion'])) . '</div>';
                $pdf->writeHTML($infoDiag, true, false, true, false, '');
                $imgRendered = true;

                // Salto de página para que el diagrama quede solo en su hoja y las fichas/notas comiencen en la hoja siguiente
                $pdf->AddPage();
            }
        }
    }

    if (!$imgRendered) {
        $htmlNoDiag = $css . '<div class="page-content"><table style="width:100%; border: 0.5px dashed #94a3b8; background-color:#f8fafc; padding:12px;">
            <tr>
                <td style="text-align:center; color:#64748b; font-size:8.5pt; padding-left:10px; padding-right:10px;">
                    <strong>Sin Diagrama de Red Cargado</strong><br>No se ha adjuntado una topología o diagrama de red para esta sede.
                </td>
            </tr>
        </table><br></div>';
        $pdf->writeHTML($htmlNoDiag, true, false, true, false, '');
    }

    // Sección B: Notas Técnicas / Fichas
    // Imprimir el título de la sección primero
    $htmlTituloNotas = $css . '<div class="page-content"><div style="font-size:10pt; font-weight:bold; color:#1e3a8a; border-bottom:2px solid #1e3a8a; padding-bottom:4px; margin-top:10px; margin-bottom:10px;">2. Memoria Técnica y Especificaciones</div></div>';
    $pdf->writeHTML($htmlTituloNotas, true, false, true, false, '');
    $pdf->Ln(4); // Espacio extra entre subtítulo y las tarjetas

    if (empty($notas)) {
        $htmlVacio = '<p style="font-size:8.5pt; color:#64748b;">No existen notas técnicas registradas para esta sede.</p>';
        $pdf->writeHTML($htmlVacio, true, false, true, false, '');
    } else {
        // Construir tarjetas HTML individuales con diseño formal
        $tarjetas = [];
        foreach ($notas as $idxNota => $n) {
            $numNota      = str_pad($idxNota + 1, 2, '0', STR_PAD_LEFT);
            $autorText    = !empty($n['usuario_nombre']) ? htmlspecialchars($n['usuario_nombre']) : 'Sin autor';
            $fechaText    = date('d/m/Y H:i', strtotime($n['fecha_actualizacion']));
            $descFormatted = nl2br(htmlspecialchars($n['descripcion']));
            $fijadoBadge  = $n['fijado']
                ? '<span class="badge-fijado">&#9733; FIJADO</span>'
                : '';

            $tarjetas[] = '
            <table class="note-card" cellpadding="0" cellspacing="0" align="left" style="width:100%; margin:0; min-height:150px; display:table;">
                <tr>
                    <td class="note-header">
                        <table style="width:100%;" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <span class="note-ref">#' . $numNota . '</span>
                                    <span class="note-title">' . htmlspecialchars($n['titulo']) . '</span>
                                    ' . $fijadoBadge . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="note-body">' . $descFormatted . '</td>
                </tr>
                <tr>
                    <td class="note-footer">
                        <table style="width:100%;" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center; padding-bottom:4px;">
                                    <span>' . $fechaText . '</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="text-align:center;">
                                    <span>' . $autorText . '</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
        }

        // Renderizar en pares dentro de una misma fila de tabla para igualar alturas
        $total = count($tarjetas);
        for ($i = 0; $i < $total; $i += 2) {
            $col1 = $tarjetas[$i];
            $col2 = isset($tarjetas[$i + 1]) ? $tarjetas[$i + 1] : null;

            if ($col2 !== null) {
                // Ambas notas en una misma fila de tabla: las celdas comparten la misma altura
                $htmlPar = $css . '
                <div class="page-content"><table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:49%; vertical-align:top; padding-right:4px; border:none; min-height:150px; display:table-cell;">' . $col1 . '</td>
                        <td style="width:2%; border:none;"></td>
                        <td style="width:49%; vertical-align:top; padding-left:4px; border:none; min-height:150px; display:table-cell;">' . $col2 . '</td>
                    </tr>
                </table></div>';
            } else {
                // Última nota sola: ancho completo
                $htmlPar = $css . '
                <div class="page-content"><table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:100%; vertical-align:top; border:none;">' . $col1 . '</td>
                    </tr>
                </table></div>';
            }

            $pdf->writeHTML($htmlPar, true, false, true, false, '');
            $pdf->Ln(1);
        }
    }

}

// Limpieza de archivos temporales
foreach ($tempFilesToDelete as $tf) {
    if (file_exists($tf)) {
        @unlink($tf);
    }
}

// Limpieza de buffer y salida del PDF
if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'Memoria_Tecnica_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre_empresa) . '.pdf';
$dest = isset($_GET['dest']) && in_array($_GET['dest'], ['I', 'D', 'F', 'S']) ? $_GET['dest'] : 'I';
return $pdf->Output($filename, $dest);
exit;