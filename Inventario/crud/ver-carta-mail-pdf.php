<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once 'tcpdf/tcpdf.php';
checkAuth();

/* =========================
   CLASE PDF PERSONALIZADA
========================= */
class MYPDF_CARTA_CUMPLIMIENTO extends TCPDF {

    private $numero_carta;

    public function setNumeroCarta($numero) {
        $this->numero_carta = $numero;
    }

    public function Header() {
        $imagePath = dirname(__FILE__) . '/img/Encabezado.png';
        if (file_exists($imagePath)) {
            $this->Image($imagePath, 5, 5, 205);
        }
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-20);
        $imagePath = dirname(__FILE__) . '/img/Pie de página.png';
        if (file_exists($imagePath)) {
            $this->Image($imagePath, 5, $this->GetY(), 205);
        }

        $this->SetFont('helvetica', '', 8);
        $this->Ln(5);
    }
}

/* =========================
   VALIDACIONES
========================= */
if (!isset($_GET['numero'])) {
    header('Location: carta-mail.php');
    exit;
}

$user_empresa_id = getCurrentUserEmpresaId();
$numero_carta = htmlspecialchars(strip_tags(trim($_GET['numero'])));

// Verificar si existe columna id_empresa
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM cartas_cumplimiento_legal LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

$query = "
SELECT c.*, 
       r.name as requester_name, r.email as requester_email, r.phone as requester_phone,
       e.nombre as company_name, eu.nombre as empresa_nombre
FROM cartas_cumplimiento_legal c
LEFT JOIN requesters r ON c.id_requester = r.id
LEFT JOIN empresas e ON r.company_id = e.id_empresa
LEFT JOIN empresas_usuarios eu ON r.id_empresa = eu.id_empresa
WHERE c.numero_carta = ?
";

// Aplicar filtro de empresa si existe la columna y el usuario tiene empresa asignada
if ($has_empresa_column && $user_empresa_id !== null) {
    $query .= " AND c.id_empresa = " . intval($user_empresa_id);
}

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $numero_carta);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Carta no encontrada o no tiene permiso para verla');
}

$carta = $result->fetch_assoc();
$stmt->close();

// Preparar datos del requester
$requester = [
    'name' => $carta['requester_name'],
    'email' => $carta['requester_email'],
    'phone' => $carta['requester_phone'],
    'company_name' => $carta['company_name'],
    'empresa_nombre' => $carta['empresa_nombre']
];

/* =========================
   PDF
========================= */
$pdf = new MYPDF_CARTA_CUMPLIMIENTO('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->setNumeroCarta($numero_carta);

$pdf->SetMargins(20, 35, 20);
$pdf->SetAutoPageBreak(true, 30);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);

$pdf->AddPage();
/* =========================
   FECHA 
========================= */
$pdf->SetFont('helvetica', '', 10);

$fechaBase = $carta['fecha_generacion'] ?? date('Y-m-d');
$timestamp = strtotime($fechaBase);

$meses = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

$dia = date('d', $timestamp);
$mes = $meses[date('n', $timestamp) - 1];
$anio = date('Y', $timestamp);

$fecha = $dia.' de '.$mes.' de '.$anio;

$pdf->MultiCell(
    0,
    5,
    'México DF, a '.$fecha.'.',
    0,
    'R'
);

$pdf->Ln(10);

/* =========================
   TÍTULO
========================= */
$pdf->SetFont('helvetica', 'B', 12.5);
$pdf->MultiCell(
    0,
    7,
    "CARTA RESPONSIVA SOBRE EL USO DEL CORREO ELECTRÓNICO INSTITUCIONAL",
    0,
    'C'
);
$pdf->Ln(8);

/* =========================
   CONTENIDO
========================= */
$pdf->SetFont('helvetica', '', 10);

$empresa = htmlspecialchars($requester['empresa_nombre'] ?? $requester['company_name'] ?? 'EMPRESA');

// Extraer el dominio del email del usuario
$dominio_email = '@dominio.com.mx'; // Valor por defecto
if (!empty($requester['email']) && filter_var($requester['email'], FILTER_VALIDATE_EMAIL)) {
    $partes_email = explode('@', $requester['email']);
    if (count($partes_email) === 2 && !empty($partes_email[1])) {
        $dominio_email = '@' . htmlspecialchars($partes_email[1]);
    }
}

$html = '
<style>
    p { text-align: justify; line-height: 1.5; }
    ol { margin-left: 20px; }
    li { margin-bottom: 6px; text-align: justify; }
    .campo { margin-top: 18px; }
    .linea { border-bottom: 1px solid #000; width: 100%; height: 14px; }
</style>

<p>
    La presente carta tiene como objetivo la definición de reglas de operación de las cuentas y contraseñas del correo electrónico institucional de <b>'.$empresa.'</b>.
</p>

<p>
    Las cláusulas aquí definidas aplican a todas las personas que tienen acceso a una cuenta de correo asignada por el Departamento de Tecnologías de la Información cuyo dominio es '.$dominio_email.'. El cumplimiento de estas reglas es responsabilidad del signatario.
</p>

<ol>
    <li>El servicio de correo electrónico deberá usarse exclusivamente para asuntos relacionados con la institución.</li>
    <li>Las cuentas de correo son personales. Por lo cual, los titulares de las cuentas son responsables directos de que se haga buen uso de las mismas.</li>
    <li>Las claves de acceso son para uso exclusivo de la persona usuaria titular y su custodia y correcta utilización son de su responsabilidad. Queda prohibido permitir su utilización a personas no autorizadas.</li>
    <li>Las personas usuarias de este servicio procederán al cambio de contraseña al recibir por primera vez su cuenta, cuando sea requerido por el sistema o cuando considere que pueda ser conocida por un tercero.</li>
    <li>Queda prohibido enviar correo electrónico no solicitado o cadenas (spamming), con propósitos comerciales, informativos, publicitarios, políticos y religiosos, entre otros; así mismo se deberá respetar la privacidad de otros usuarios.</li>
    <li>Queda prohibido el uso de cuentas de correo electrónico por parte de personas distintas al titular de la misma, por lo que las cuentas y contraseñas son intransferibles.</li>
    <li>Es responsabilidad de los titulares de las cuentas de correo electrónico respaldar la información (mensajes) en medios magnéticos u ópticos.</li>
    <li>Estas cuentas son un medio de comunicación de los colaboradores de la empresa, por lo que el titular está obligado a consultarla periódicamente.</li>
</ol>

<p>
    He leído cada una de las cláusulas y las acepto de conformidad con las políticas que rigen a estos Servicios de '.$empresa.'.
</p>

<br><br>

<table width="100%">
    <tr>
        <td width="30%">Nombre del usuario:</td>
        <td width="70%"><div class="linea"></div></td>
    </tr>
    <tr>
        <td>Área de adscripción:</td>
        <td><div class="linea"></div></td>
    </tr>
    <tr>
        <td>Cuenta de correo:</td>
        <td><div class="linea"></div></td>
    </tr>
    
</table>

<br><br><br>

<table width="100%">
    <tr>
        <td width="35%"></td>
        <td width="30%" align="center" style="border-top:1px solid #000;"></td>
        <td width="35%"></td>
    </tr>
    <tr>
        <td colspan="3" align="center" style="font-size:9pt; padding-top:4px;">
            Firma del usuario
        </td>
    </tr>
</table>



';

$pdf->writeHTML($html, true, false, true, false, '');

if (ob_get_length()) ob_end_clean();
$pdf->Output('Carta_Responsiva_'.$numero_carta.'.pdf', 'I');
exit;
?>

