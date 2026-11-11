<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../Inventario/crud/tcpdf/tcpdf.php';
checkAuth();

/* =========================
   CLASE PDF PERSONALIZADA
========================= */
class MYPDF_CARTA_ENTREGA extends TCPDF {

    private $numero_carta;

    public function setNumeroCarta($numero) {
        $this->numero_carta = $numero;
    }

    public function Header() {
        $imagePath = dirname(dirname(dirname(__FILE__))) . '/../Inventario/crud/img/Encabezado.png';
        if (file_exists($imagePath)) {
            $this->Image($imagePath, 5, 5, 205);
        }
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-20);
        $imagePath = dirname(dirname(dirname(__FILE__))) . '/../Inventario/crud/img/Pie de página.png';
        if (file_exists($imagePath)) {
            $this->Image($imagePath, 5, $this->GetY(), 205);
        }

        $this->SetFont('helvetica', '', 8);
        $this->Ln(5);
        //$this->Cell(0, 5, 'Folio: ' . $this->numero_carta . ' | Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

/* =========================
   VALIDACIONES
========================= */
if (!isset($_GET['numero'])) {
    header('Location: cartas-entrega.php');
    exit;
}

$user_empresa_id = getCurrentUserEmpresaId();
$numero_carta = limpiarDatos($_GET['numero']);

// Verificar si existe columna id_empresa
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

$query = "
SELECT ce.*, 
       p.nombre_producto, p.codigo_producto, p.marca, p.modelo, p.unidad,
       a.nombre AS almacen_nombre
FROM cartas_entrega ce
LEFT JOIN productos_almacen p ON ce.id_producto = p.id_producto
LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen
WHERE ce.numero_carta = ?
";

// Aplicar filtro de empresa si existe la columna y el usuario tiene empresa asignada
if ($has_empresa_column && $user_empresa_id !== null) {
    $query .= " AND ce.id_empresa = " . intval($user_empresa_id);
}

$query .= " ORDER BY ce.id_carta";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $numero_carta);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Carta no encontrada o no tiene permiso para verla');
}

$productos = [];
$info = null;
while ($row = $result->fetch_assoc()) {
    if (!$info) $info = $row;
    $productos[] = $row;
}

/* =========================
   PDF
========================= */
$pdf = new MYPDF_CARTA_ENTREGA('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->setNumeroCarta($numero_carta);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);

/* =========================
   AJUSTE RESPONSIVO PARA UNA SOLA PÁGINA
   Calcula un "peso" del contenido considerando:
   - Productos (cada uno cuenta como 1)
   - Observaciones (cada 80 caracteres = 1)
   Esto garantiza que el contenido nunca invada
   la zona de firmas, sin importar si hay muchos
   productos o si las observaciones son extensas.
========================= */
$num_productos    = count($productos);
$len_observaciones = isset($info['observaciones']) ? strlen(trim($info['observaciones'])) : 0;
$peso_contenido   = $num_productos + (int)ceil($len_observaciones / 80);

if ($peso_contenido <= 5) {
    // Diseño normal (poco contenido)
    $cfg = [
        'mt' => 30,
        'banner_pad' => 8, 'banner_fs' => 18, 'banner_sub_fs' => 10,
        'sec_fs' => 11,
        'recep_pad' => 4, 'recep_fs' => 9.5,
        'prod_pad' => 4, 'prod_fs' => 9,
        'total_pad' => 7, 'total_fs_lbl' => 9, 'total_fs_num' => 17,
        'obs_pad' => 6, 'obs_fs' => 9.5,
        'resp_pad' => 5, 'resp_fs' => 8.5,
        'firmas_fs' => 9, 'firmas_lbl_fs' => 10, 'firmas_sub_fs' => 8.5,
        'ln_sec' => 3, 'ln_chico' => 2,
    ];
} elseif ($peso_contenido <= 9) {
    // Compacto ligero
    $cfg = [
        'mt' => 28,
        'banner_pad' => 5, 'banner_fs' => 15, 'banner_sub_fs' => 9.5,
        'sec_fs' => 10.5,
        'recep_pad' => 3, 'recep_fs' => 9,
        'prod_pad' => 3, 'prod_fs' => 8.5,
        'total_pad' => 5, 'total_fs_lbl' => 8.5, 'total_fs_num' => 14,
        'obs_pad' => 4, 'obs_fs' => 8.5,
        'resp_pad' => 4, 'resp_fs' => 8,
        'firmas_fs' => 9, 'firmas_lbl_fs' => 10, 'firmas_sub_fs' => 8.5,
        'ln_sec' => 2, 'ln_chico' => 1.5,
    ];
} elseif ($peso_contenido <= 14) {
    // Compacto medio
    $cfg = [
        'mt' => 25,
        'banner_pad' => 4, 'banner_fs' => 13, 'banner_sub_fs' => 9,
        'sec_fs' => 10,
        'recep_pad' => 2.5, 'recep_fs' => 8.5,
        'prod_pad' => 2.2, 'prod_fs' => 8,
        'total_pad' => 4, 'total_fs_lbl' => 8, 'total_fs_num' => 12,
        'obs_pad' => 3, 'obs_fs' => 8,
        'resp_pad' => 3, 'resp_fs' => 7.5,
        'firmas_fs' => 8.5, 'firmas_lbl_fs' => 9.5, 'firmas_sub_fs' => 8,
        'ln_sec' => 1.5, 'ln_chico' => 1,
    ];
} else {
    // Ultra compacto
    $cfg = [
        'mt' => 22,
        'banner_pad' => 2, 'banner_fs' => 12, 'banner_sub_fs' => 8.5,
        'sec_fs' => 9.5,
        'recep_pad' => 1.8, 'recep_fs' => 8,
        'prod_pad' => 1.5, 'prod_fs' => 7.5,
        'total_pad' => 3, 'total_fs_lbl' => 7.5, 'total_fs_num' => 11,
        'obs_pad' => 2.5, 'obs_fs' => 7.5,
        'resp_pad' => 2.5, 'resp_fs' => 7,
        'firmas_fs' => 8, 'firmas_lbl_fs' => 9, 'firmas_sub_fs' => 7.5,
        'ln_sec' => 1, 'ln_chico' => 0.5,
    ];
}

// Aplicar márgenes dinámicos y desactivar salto automático para forzar una sola página
$pdf->SetMargins(15, $cfg['mt'], 15);
$pdf->SetAutoPageBreak(false, 15);

$pdf->AddPage();

/* =========================
   CÁLCULOS DE RESUMEN
========================= */
$total_unidades  = 0;
foreach ($productos as $p) {
    $total_unidades += (float)$p['cantidad_entregada'];
}
$total_unidades = ($total_unidades == (int)$total_unidades)
    ? (int)$total_unidades
    : number_format($total_unidades, 2, '.', '');

/* =========================
   BANNER DE TÍTULO
========================= */
$html = '
<table cellpadding="' . $cfg['banner_pad'] . '" cellspacing="0" style="width:100%;">
    <tr>
        <td align="center" bgcolor="#0B3D91" style="color:#FFFFFF; border:1px solid #0B3D91;">
            <span style="font-size:' . $cfg['banner_fs'] . 'pt; font-weight:bold; letter-spacing:2px;">CARTA DE ENTREGA</span>
        </td>
    </tr>
    <tr>
        <td align="center" bgcolor="#F2F6FC" style="border:1px solid #DBE4F0; color:#0B3D91;">
            <span style="font-size:' . $cfg['banner_sub_fs'] . 'pt; font-weight:bold;">Folio:</span>
            <span style="font-size:' . $cfg['banner_sub_fs'] . 'pt; color:#333333;"> ' . htmlspecialchars($numero_carta) . ' </span>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span style="font-size:' . $cfg['banner_sub_fs'] . 'pt; font-weight:bold;">Fecha:</span>
            <span style="font-size:' . $cfg['banner_sub_fs'] . 'pt; color:#333333;"> ' . formatearFechaAlmacen($info['fecha_entrega']) . ' </span>
        </td>
    </tr>
</table>
';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln($cfg['ln_sec']);

/* =========================
   DATOS RECEPTOR
========================= */
$html = '
<table cellpadding="0" cellspacing="0" style="width:100%;">
    <tr>
        <td bgcolor="#0B3D91" style="color:#FFFFFF; border:1px solid #0B3D91;" align="left">
            <span style="font-size:' . $cfg['sec_fs'] . 'pt; font-weight:bold;">&nbsp;&nbsp;INFORMACIÓN DEL RECEPTOR</span>
        </td>
    </tr>
</table>

<table cellpadding="' . $cfg['recep_pad'] . '" cellspacing="0" style="width:100%; font-size:' . $cfg['recep_fs'] . 'pt;">
    <tr>
        <td width="22%" bgcolor="#F2F6FC" style="border:1px solid #DBE4F0; color:#0B3D91; font-weight:bold;">Nombre</td>
        <td width="28%" style="border:1px solid #DBE4F0;">' . htmlspecialchars($info['nombre_receptor']) . '</td>
        <td width="22%" bgcolor="#F2F6FC" style="border:1px solid #DBE4F0; color:#0B3D91; font-weight:bold;">Identificación</td>
        <td width="28%" style="border:1px solid #DBE4F0;">' . htmlspecialchars($info['identificacion_receptor']) . '</td>
    </tr>
    <tr>
        <td bgcolor="#F2F6FC" style="border:1px solid #DBE4F0; color:#0B3D91; font-weight:bold;">Empresa</td>
        <td style="border:1px solid #DBE4F0;">' . htmlspecialchars($info['empresa_receptor']) . '</td>
        <td bgcolor="#F2F6FC" style="border:1px solid #DBE4F0; color:#0B3D91; font-weight:bold;">Área</td>
        <td style="border:1px solid #DBE4F0;">' . htmlspecialchars($info['area_receptor'] ?? 'N/A') . '</td>
    </tr>
    <tr>
        <td bgcolor="#F2F6FC" style="border:1px solid #DBE4F0; color:#0B3D91; font-weight:bold;">Fecha de Entrega</td>
        <td style="border:1px solid #DBE4F0;">' . formatearFechaAlmacen($info['fecha_entrega']) . '</td>
        <td bgcolor="#F2F6FC" style="border:1px solid #DBE4F0; color:#0B3D91; font-weight:bold;">Tipo de Entrega</td>
        <td style="border:1px solid #DBE4F0;">' . htmlspecialchars($info['tipo_entrega']) . '</td>
    </tr>
</table>
';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln($cfg['ln_sec']);

/* =========================
   DETALLE DE PRODUCTOS
========================= */
$html = '
<table cellpadding="0" cellspacing="0" style="width:100%;">
    <tr>
        <td bgcolor="#0B3D91" style="color:#FFFFFF; border:1px solid #0B3D91;" align="left">
            <span style="font-size:' . $cfg['sec_fs'] . 'pt; font-weight:bold;">&nbsp;&nbsp;DETALLE DE PRODUCTOS ENTREGADOS</span>
        </td>
    </tr>
</table>

<table cellpadding="' . $cfg['prod_pad'] . '" cellspacing="0" style="width:100%; font-size:' . $cfg['prod_fs'] . 'pt;">
    <thead>
        <tr bgcolor="#0B3D91" style="color:#FFFFFF;">
            <th width="15%" style="border:1px solid #0B3D91; font-weight:bold;" align="center">Código</th>
            <th width="45%" style="border:1px solid #0B3D91; font-weight:bold;" align="center">Descripción</th>
            <th width="25%" style="border:1px solid #0B3D91; font-weight:bold;" align="center">Marca / Modelo</th>
            <th width="15%" style="border:1px solid #0B3D91; font-weight:bold;" align="center">Cantidad</th>
        </tr>
    </thead>
    <tbody>
';

$i = 0;
foreach ($productos as $p) {

    $codigo      = htmlspecialchars($p['codigo_producto']);
    $descripcion = htmlspecialchars($p['nombre_producto']);
    $marcaModelo = htmlspecialchars(trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '')));
    if ($marcaModelo === '') $marcaModelo = '—';
    $cantidad    = htmlspecialchars($p['cantidad_entregada'] . ' ' . $p['unidad']);

    $bg = ($i % 2 === 0) ? '#FFFFFF' : '#F7FAFF';
    $i++;

    $html .= '
    <tr>
        <td width="15%" bgcolor="' . $bg . '" style="border:1px solid #DBE4F0;" align="center">' . $codigo . '</td>
        <td width="45%" bgcolor="' . $bg . '" style="border:1px solid #DBE4F0;">' . $descripcion . '</td>
        <td width="25%" bgcolor="' . $bg . '" style="border:1px solid #DBE4F0;">' . $marcaModelo . '</td>
        <td width="15%" bgcolor="' . $bg . '" style="border:1px solid #DBE4F0;" align="center"><b>' . $cantidad . '</b></td>
    </tr>';
}

$html .= '
    </tbody>
</table>
';

$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln($cfg['ln_chico']);

/* =========================
   RESUMEN (TARJETA)
========================= */
$html = '
<table cellpadding="' . $cfg['total_pad'] . '" cellspacing="0" style="width:100%;">
    <tr>
        <td width="100%" bgcolor="#F2F6FC" align="center" style="border:1px solid #DBE4F0;">
            <span style="font-size:' . $cfg['total_fs_lbl'] . 'pt; color:#666666; font-weight:bold;">TOTAL DE UNIDADES</span><br>
            <span style="font-size:' . $cfg['total_fs_num'] . 'pt; color:#0B3D91; font-weight:bold;">' . $total_unidades . '</span>
        </td>
    </tr>
</table>
';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln($cfg['ln_chico']);

/* =========================
   OBSERVACIONES
========================= */
if (!empty($info['observaciones'])) {
    $html = '
    <table cellpadding="0" cellspacing="0" style="width:100%;">
        <tr>
            <td bgcolor="#0B3D91" style="color:#FFFFFF; border:1px solid #0B3D91;" align="left">
                <span style="font-size:' . $cfg['sec_fs'] . 'pt; font-weight:bold;">&nbsp;&nbsp;OBSERVACIONES</span>
            </td>
        </tr>
    </table>

    <table cellpadding="' . $cfg['obs_pad'] . '" cellspacing="0" style="width:100%; font-size:' . $cfg['obs_fs'] . 'pt;">
        <tr>
            <td bgcolor="#FAFAFA" style="border:1px solid #DBE4F0; line-height:1.4;">
                ' . nl2br(htmlspecialchars($info['observaciones'])) . '
            </td>
        </tr>
    </table>
    ';
    $pdf->writeHTML($html, true, false, false, false, '');
    $pdf->Ln($cfg['ln_chico']);
}

/* =========================
   POSICIONAMIENTO DE RESPONSIVA
   La responsiva queda justo antes de las firmas (que
   están fijas a 40mm del fondo). Si todavía hay mucho
   espacio, la subimos para que quede pegada arriba
   de las firmas y no flote a la mitad de la página.
========================= */
$y_firmas_inicio = $pdf->getPageHeight() - 40; // donde empiezan las firmas
$alto_responsiva = 22; // mm aproximados de la responsiva
$gap_min = 2; // mm de margen entre responsiva y firmas
$y_objetivo_resp = $y_firmas_inicio - $alto_responsiva - $gap_min;

if ($pdf->GetY() < $y_objetivo_resp) {
    $pdf->SetY($y_objetivo_resp);
}

/* =========================
   CLÁUSULA DE RESPONSABILIDAD
========================= */
$html = '
<table cellpadding="' . $cfg['resp_pad'] . '" cellspacing="0" style="width:100%; font-size:' . $cfg['resp_fs'] . 'pt;">
    <tr>
        <td bgcolor="#FFFBE6" style="border:1px solid #F5D26B; color:#5C4400; line-height:1.4;" align="justify">
            <b>RESPONSIVA:</b> El receptor declara haber recibido, a entera satisfacción, los bienes descritos en la presente carta de entrega, comprometiéndose a darles el uso adecuado y a responder por su correcto resguardo, conservación y devolución, en las condiciones acordadas.
        </td>
    </tr>
</table>
';
$pdf->writeHTML($html, true, false, false, false, '');

/* =========================
   FIRMAS - posición ABSOLUTA antes del footer
   Garantizamos que SIEMPRE se vean, sin importar lo
   que haya arriba. 40mm desde el bottom = zona segura.
========================= */
$y_firmas = $pdf->getPageHeight() - 40; // 40mm desde el final (sobre el footer)
$pdf->SetY($y_firmas);

$html = '
<table cellpadding="0" cellspacing="0" style="width:100%; font-size:' . $cfg['firmas_fs'] . 'pt;">
    <tr>
        <td width="45%" align="center">
            <span style="color:#0B3D91;">_______________________________________</span><br>
            <span style="font-weight:bold; color:#0B3D91; font-size:' . $cfg['firmas_lbl_fs'] . 'pt;">RECIBIÓ</span><br>
            <span style="color:#666666; font-size:' . $cfg['firmas_sub_fs'] . 'pt;">Nombre y firma</span>
        </td>
        <td width="10%">&nbsp;</td>
        <td width="45%" align="center">
            <span style="color:#0B3D91;">_______________________________________</span><br>
            <span style="font-weight:bold; color:#0B3D91; font-size:' . $cfg['firmas_lbl_fs'] . 'pt;">ENTREGÓ</span><br>
            <span style="color:#666666; font-size:' . $cfg['firmas_sub_fs'] . 'pt;">Nombre y firma</span>
        </td>
    </tr>
</table>
';
$pdf->writeHTML($html, true, false, false, false, '');

if (ob_get_length()) ob_end_clean();
$pdf->Output('Carta_Entrega_' . $numero_carta . '.pdf', 'I');
exit;
