<?php
ob_start();
global $numero_reporte;

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');

ini_set('log_errors', 1);
ini_set('error_log', 'error_pdf.log');

if (!class_exists('TCPDF')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    
    if (!defined('K_PATH_MAIN')) {
        define('K_PATH_MAIN', dirname(dirname(dirname(__FILE__))) . '/tcpdf/');
    }
    
    $tcpdf_path = dirname(dirname(dirname(__FILE__))) . '/tcpdf/tcpdf.php';
    
    file_put_contents('debug.log', "Loading TCPDF from: $tcpdf_path\n", FILE_APPEND);
    
    if (file_exists($tcpdf_path)) {
        require_once($tcpdf_path);
        file_put_contents('debug.log', "TCPDF loaded successfully\n", FILE_APPEND);
    } else {
        die("TCPDF library not found at: $tcpdf_path");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_pdf'])) {
    echo htmlspecialchars($numeroReporte);
}

class MYPDF extends TCPDF {
    private $numeroReporte;
    
    public function setNumeroReporte($numero) {
        $this->numeroReporte = $numero;
    }

    public function Footer() {
        $this->SetY(-22);
        
        $imagePath = dirname(dirname(dirname(__FILE__))) . '/crud/img/Pie de página.png';
        file_put_contents('debug.log', "Ruta de imagen pie: $imagePath - Existe: " . (file_exists($imagePath) ? 'Sí' : 'No') . "\n", FILE_APPEND);
        
        if (file_exists($imagePath)) {
            $this->Image($imagePath, 0.5, $this->GetY(), 215);
        } else {
            $this->Cell(0, 10, 'Imagen no encontrada: ' . $imagePath, 0, false, 'L');
        }
        
        $this->SetFont('helvetica', 'I', 8);
        
        $this->Cell(0, 0, '', 0, 'C');
        $this->Ln(1);
        
    }
    
    public function Header() {
        $this->SetFont('helvetica', 'B', 10);
        
        $imagePath = dirname(dirname(dirname(__FILE__))) . '/crud/img/Encabezado.png';
        file_put_contents('debug.log', "Ruta de imagen cabecera: $imagePath - Existe: " . (file_exists($imagePath) ? 'Sí' : 'No') . "\n", FILE_APPEND);
        
        if (file_exists($imagePath)) {
            $this->Image($imagePath, 0.5, $this->GetY(), 215);
        } else {
            $this->Cell(0, 10, 'Imagen no encontrada: ' . $imagePath, 0, false, 'L');
        }
        
        $this->SetY(15);
        $this->SetX(70);
        
        $this->SetY(35);
        $this->Cell(0, 0, '', 0, 'C');
        
        $this->SetY(15);
        $this->SetX(70);
        $this->SetFont('dejavusans', 'B', 12);
        $this->Cell(50, 8, '' . $this->numeroReporte, 0, 0, 'R', false, '', 0, false, 'T', 'M');
    }
}

try {
    file_put_contents('debug.log', "Iniciando generación de PDF\n");

    echo htmlspecialchars($numeroReporte);

    $responsable = isset($_POST['responsable']) ? $_POST['responsable'] : ""; 
    $numero_empleado = isset($_POST['numero_empleado']) ? $_POST['numero_empleado'] : "";
    $primer_equipo = !empty($equipos) ? $equipos[0] : null;
    $empresa_nombre = isset($_POST['empresa_nombre']) && $_POST['empresa_nombre'] !== '' && $_POST['empresa_nombre'] !== 'Todas las empresas' 
        ? $_POST['empresa_nombre'] 
        : ($primer_equipo && !empty($primer_equipo['nombre_cliente']) ? $primer_equipo['nombre_cliente'] : 'SecuriTI');
    $empresa_nombre_pdf = htmlspecialchars($empresa_nombre);
    $status = isset($_POST['estatus']) ? $_POST['estatus'] : "";

    
    $pdf = new MYPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    file_put_contents('debug.log', "Objeto PDF creado\n", FILE_APPEND);

    if (!isset($numeroReporte) || $numeroReporte === '') {
        $numeroReporte = isset($numero_reporte) ? $numero_reporte : 'CR-000';
    }

    $pdf->SetCreator('Sistema de Inventario SecuriTI');
    $pdf->SetAuthor('SecuriTI');
    $pdf->SetTitle('Carta Responsiva de Equipos - ' . $numeroReporte);
    $pdf->SetSubject('Carta Responsiva de Equipos');
    $pdf->SetKeywords('SecuriTI, Carta, Responsiva, Equipos, Inventario');

    $pdf->SetMargins(15, 27, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    $pdf->SetAutoPageBreak(TRUE, 25);

    $pdf->setImageScale(5);

    $pdf->SetFont('helvetica', '', 10);

    $pdf->AddPage();
    $pdf->SetY(40);
    file_put_contents('debug.log', "Página añadida\n", FILE_APPEND);

    function obtenerNombreMes($mes) {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        return $meses[(int)$mes];
    }

    $dia = date('d');
    $mes = obtenerNombreMes(date('m'));
    $anio = date('Y');
    $fecha_formateada = "{$dia} de {$mes} de {$anio}";
    
    if (!isset($equipos) || !is_array($equipos)) {
        $equipos = []; 
        file_put_contents('debug.log', "Variable \$equipos no definida o no es un array, se inicializó como array vacío\n", FILE_APPEND);
    }
    $html = '
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 12pt;
            text-align: justify;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        table th, table td {
            border: 1px solid #003366;
            padding: 5px;
        }
        th {
            background-color: #e6eef9;
            color: #003366;
            font-weight: bold;
        }
        .firmas-container {
            width: 100%;
            margin-top: 40px;
        }
        .firma-group {
            text-align: center;
            width: 100%;
        }
        .firma-texto {
            margin-top: 5px;
            font-weight: bold;
        }
        .fecha {
            text-align: right;
            font-style: italic;
            font-size: 12pt;
        }
        .titulo {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            color: #003366;
            margin-bottom: 15px;
        }
        .subtitulo {
            font-size: 14pt;
            font-weight: bold;
            color: #003366;
        }
        .parrafo {
            text-align: justify;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        p{
            font-size: 12pt;
            text-align: justify;
            line-height: 1.5;
        }
        .text-justify {
            text-align: justify;
        }
        .text-center {
            text-align: center;
        }

    </style>
    <div class="fecha" style="margin-top: 20px;">
        ' . $numeroReporte . '
    </div>
    <div class="titulo">CARTA RESPONSIVA DE EQUIPO</div>
    <br>
    <div class="fecha" style="margin-top: 20px;">
        ' . $fecha_formateada . '
    </div>
    <p><strong>A QUIEN CORRESPONDA:</strong></p>
    <br>
    <p style="text-align: justify; line-height: 1.5;">
    Por medio de la presente, yo, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        &nbsp; hago constar que recibo en calidad de préstamo el siguiente equipo propiedad de 
        <strong>Luis Alberto Jijón Jiménez</strong> a la empresa <strong>' . htmlspecialchars($empresa_nombre) . '</strong>:

    </p>
    <br>
    <table> 
        <thead> 
            <tr> 
                <th>Equipo</th> 
                <th>Marca</th> 
                <th>Modelo</th> 
                <th>Número de Serie</th> 
                <th>Estado</th> 
            </tr> 
        </thead> 
        <tbody>';

    foreach ($equipos as $equipo) {
        $html .= '<tr> 
            <td>' . htmlspecialchars($equipo['tipo']) . '</td> 
            <td>' . htmlspecialchars($equipo['fabricante']) . '</td> 
            <td>' . htmlspecialchars($equipo['modelo']) . '</td> 
            <td>' . htmlspecialchars($equipo['numero_serie']) . '</td> 
            <td>' . htmlspecialchars($equipo['estatus']) . '</td> 
        </tr>';
    }

    $html .= '</tbody> 
    </table> 
    <br><br>
    <p> 
        <br><br><br>
    </p>
    <p> 
        <br><br><br>
    </p>
    <br><br>
    <br><br>
    <br><br>';
file_put_contents('debug_html.html', $html);
    
    $pdf->setHtmlVSpace(array(
        'p' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
        'h1' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
        'table' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0))
    ));
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    $pdf->SetFont('helvetica', 'B', 16); 
    $pdf->SetY(40);
    $pdf->SetX(15);
    $pdf->writeHTML(htmlspecialchars($equipo['nombre_cliente']));

    $pdf->SetFont('helvetica', '', 12);

    $pdf->SetLineStyle(array('width' => 0.5, 'dash' => '5,0'));
    $pdf->Line(75, 87, $pdf->GetPageWidth() - 91, 87);
    
    $pdf->SetY(113);
    $pdf->SetX(167);
    $pdf->TextField('estatus', 30, 5, array(
        'strokeColor'=>array(0,51,102), 
        'lineWidth'=>0, 
        'borderStyle'=>'underline',
        'fontsize'=>12,
        'fontstyle'=>''
    ));

    $pdf->SetY(125);
    $pdf->SetX(15);
    $pdf->setCellHeightRatio(1.5);
$pdf->writeHTML('<div style="text-align: justify;">
                        Declaro que el equipo se encuentra en óptimas condiciones al momento de la recepción y me
                        comprometo a darle un uso adecuado, evitando daños o pérdidas. En caso de extravío, robo o
                        daño, me haré responsable de cubrir el costo de reparación o reposición según lo determine
                        <b>Luis Alberto Jijón Jiménez</b>.
                        <br><br>
                        Así mismo, acepto que el equipo deberá ser devuelto en las mismas condiciones en las que fue
                        recibido, en la fecha acordada o cuando
                        <b>Luis Alberto Jijón Jiménez</b> lo requiera.
                     </div>');
    
    $pdf->SetY(175);
    $pdf->SetX(17);
    $pdf->SetFont('helvetica', 'B', 12); 
    $pdf->SetTextColor(0, 51, 102); 
    $pdf->SetFillColor(230, 238, 249); 
    $pdf->Cell(184, 5, 'Observaciones', 1, 1, 'C', 1, '', 0, false, 'T', 'M');
    
    $pdf->SetY(181.5);
    $pdf->SetX(17);
    $pdf->SetFont('helvetica', '', 12); 
    $pdf->SetTextColor(0, 0, 0); 
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(184, 21, '', 1, 1, 'C', 0, '', 0, false, 'T', 'M');

    $pdf->SetY(183);
    $pdf->SetX(20);
    $pdf->TextField('observaciones', 178, 17, array(
        'strokeColor'=>array(0,51,102),
        'lineWidth'=>0,
        'borderStyle'=>'underline',
        'fontsize'=>10,
        'fontstyle'=>'',
        'multiline' => true,
        'doNotScroll' => false
    ));
    
    $pdf->SetY(211);
        $pdf->SetX(44);
        $pdf->TextField('firma_recibido', 38, 7, array(
            'strokeColor'=>array(0,51,102), 
            'lineWidth'=>0, 
            'borderStyle'=>'underline',
            'fontsize'=>12,
            'fontstyle'=>'',
            'alignment' => 'center',
            'da' => '0 51 102 rg 0 Tc 0 Tw 100 Tz 0 TL 0 Ts 0 Tr /Helvetica 12 Tf'
        ));
        
        $pdf->SetLineStyle(array('width' => 0.5, 'dash' => '5,0'));
        $pdf->Line(41, 220, $pdf->GetPageWidth() - 131, 220);
        
        $pdf->SetY(215);
        $pdf->SetX(37);
        $pdf->writeHTML('Nombre y firma de recibido');
        
        $pdf->SetY(211);
        $pdf->SetX(136);
        $pdf->TextField('firma_entregado', 38, 7, array(
            'strokeColor'=>array(0,51,102), 
            'lineWidth'=>0, 
            'borderStyle'=>'underline',
            'fontsize'=>12,
            'fontstyle'=>'',
            'alignment' => 'center',
            'da' => '0 0 0 rg 0 Tc 0 Tw 100 Tz 0 TL 0 Ts 0 Tr /Helvetica 12 Tf'
        ));
        
        $pdf->SetLineStyle(array('width' => 0.5, 'dash' => '5,0'));
        $pdf->Line(131, 220, $pdf->GetPageWidth() - 36, 220);
        
        $pdf->SetY(215);
        $pdf->SetX(128);
        $pdf->writeHTML('Nombre y firma de entregado');

    
    file_put_contents('debug.log', "Generando PDF final\n", FILE_APPEND);
    
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    $pdf->Output('carta_responsiva_equipos_' . $numeroReporte . '.pdf', 'I');
    exit;
} catch (Exception $e) {
    $errorMsg = 'Error al generar el PDF: ' . $e->getMessage() . ' en ' . $e->getFile() . ' línea ' . $e->getLine();
    error_log($errorMsg);
    echo $errorMsg;
}
?>