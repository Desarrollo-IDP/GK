<?php
ob_start();
global $numeroReporte;

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');

if (!class_exists('TCPDF')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    if (!defined('K_PATH_MAIN')) {
        define('K_PATH_MAIN', dirname(dirname(dirname(__FILE__))) . '/tcpdf/');
    }
    $tcpdf_path = dirname(dirname(dirname(__FILE__))) . '/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once($tcpdf_path);
    } else {
        die("TCPDF library not found at: $tcpdf_path");
    }
}

class MYPDF extends TCPDF {
    public $numeroReporte;
    public function Footer() {
        // No footer needed as per the image layout or kept minimal
    }
    
    public function Header() {
        // Custom header with logo and report number/date
    }
}

try {
    // Extract data from $data array (passed from generar_reporte.php)
    $marca = $data['entrada_marca'] ?? '';
    $empresa = $data['entrada_cliente'] ?? '';
    $modelo = $data['entrada_modelo'] ?? '';
    $responsable = $data['entrada_responsable'] ?? '';
    $serie = $data['entrada_serie'] ?? '';
    $restauracion = $data['entrada_restauracion'] ?? '';
    $contrasena = $data['entrada_contrasena'] ?? '';
    $categoria = $data['mantenimiento_categoria'] ?? 'Laptop';
    $nombre_equipo = $data['entrada_nombre_equipo'] ?? '';
    
    // Aditamentos variables
    $cargador = $data['entrada_cargador'] ?? '';
    $bateria = $data['entrada_bateria'] ?? '';
    $cable_ac_l = $data['entrada_cable_ac'] ?? '';
    $monitor = $data['entrada_monitor'] ?? '';
    $teclado = $data['entrada_teclado'] ?? '';
    $mouse = $data['entrada_mouse'] ?? '';
    $aio = $data['entrada_aio'] ?? '';
    $cable_ac_pc = $data['entrada_cable_ac_pc'] ?? '';
    $cable_video = $data['entrada_cable_video'] ?? '';
    
    // Printer specific
    $usb = $data['entrada_usb'] ?? '';
    $toner = $data['entrada_toner'] ?? '';
    $tinta = $data['entrada_tinta'] ?? '';
    $cable_ac_imp = $data['entrada_cable_ac_imp'] ?? '';
    
    $test_enciende = $data['test_enciende'] ?? '';
    $test_escaneo = $data['test_escaneo'] ?? '';
    $test_copiado = $data['test_copiado'] ?? '';
    $test_firmware = $data['test_firmware'] ?? '';
    
    // Lab Aditaments
    $adit_impresora = $data['adit_impresora'] ?? '';

    $disco_duro = $data['entrada_disco_duro'] ?? '';
    $memoria_ram = $data['entrada_memoria_ram'] ?? '';
    $falla_reportada = $data['falla_reportada'] ?? '';
    $otros = $data['entrada_otros'] ?? '';
    
    // Checklist Logic based on category
    if ($categoria === 'PC' || $categoria === 'PC All in One') {
        $condiciones = [
            'EQUIPO RAYADO O MALTRATADO' => $data['cond_rayado_pc'] ?? '',
            'CARGADOR GOLPEADO' => $data['cond_cargador_pc'] ?? '',
            'TORNILLOS COMPLETOS' => $data['cond_tornillos_pc'] ?? '',
            'MONITOR RAYADO O MALTRATADO' => $data['cond_monitor_pc'] ?? '',
            'TAPAS O PROTECCIONES' => $data['cond_tapas_pc'] ?? '',
            'MUESTRA DERRAME DE LIQUIDOS' => $data['cond_liquidos_pc'] ?? '',
            'RESPALDO DE LA INFORMACION' => $data['cond_respaldo_pc'] ?? '',
            'CABLE AC MALTRATADO O ROTO' => $data['cond_cable_ac_pc'] ?? '',
            'CABLE DE VIDEO MALTRATADO O ROTO' => $data['cond_cable_video_pc'] ?? ''
        ];
    } elseif ($categoria === 'Impresora') {
        $condiciones = [
            'EQUIPO RAYADO O MALTRATADO' => $data['cond_rayado_imp'] ?? '',
            'EQUIPO GOLPEADO' => $data['cond_golpeado_imp'] ?? '',
            'TORNILLOS INCOMPLETOS' => $data['cond_tornillos_imp'] ?? '',
            'MUESTRA INTERVENCION EL EQUIPO' => $data['cond_intervencion_imp'] ?? '',
            'TAPAS O PROTECCIONES' => $data['cond_tapas_imp'] ?? '',
            'MUESTRA DERRAME DE LIQUIDOS' => $data['cond_liquidos_imp'] ?? '',
            'CABLE AC MALTRATADO O ROTO' => $data['cond_cable_ac_imp_br'] ?? '',
            'CABLE USB MALTRATADO O ROTO' => $data['cond_usb_br'] ?? ''
        ];
    } elseif ($categoria === 'Salida a Laboratorio') {
        $condiciones = [
            'EQUIPO RAYADO O MALTRATADO' => $data['cond_rayado_lab'] ?? '',
            'PANTALLA RAYADA' => $data['cond_pantalla_lab'] ?? '',
            'TORNILLOS INCOMPLETOS' => $data['cond_tornillos_lab'] ?? '',
            'MONITOR RAYADO O MALTRATADO' => $data['cond_monitor_lab'] ?? '',
            'TAPAS O PROTECCIONES' => $data['cond_tapas_lab'] ?? '',
            'MUESTRA DERRAME DE LIQUIDOS' => $data['cond_liquidos_lab'] ?? ''
        ];
    } else {
        $condiciones = [
            'EQUIPO RAYADO O MALTRATADO' => $data['cond_rayado'] ?? '',
            'EQUIPO GOLPEADO' => $data['cond_golpeado'] ?? '',
            'TORNILLOS INCOMPLETOS' => $data['cond_tornillos_l'] ?? '',
            'GOMAS DE DISPLAY COMPLETAS' => $data['cond_gomas'] ?? '',
            'RESPALDO DE LA INFORMACION' => $data['cond_respaldo_l'] ?? '',
            'MUESTRA DERRAME DE LIQUIDOS' => $data['cond_liquidos_l'] ?? '',
            'TECLADO COMPLETO' => $data['cond_teclado_l'] ?? '',
            'CABLE AC MALTRATADO O ROTO' => $data['cond_cable_ac_l'] ?? '',
            'CARGADOR MALTRATADO O ROTO' => $data['cond_cargador_l'] ?? '',
            'MUESTRA INTERVENCION DEL EQUIPO' => $data['cond_intervencion_l'] ?? ''
        ];
    }

    $salida = [
        'EQUIPO BIEN ARMADO' => $data['salida_armado'] ?? '',
        'LIMPIEZA DE EQUIPO' => $data['salida_limpieza'] ?? '',
        'INSTALACION DE ANTIVIRUS / CONFIG' => $data['salida_antivirus'] ?? '',
        'EQUIPO ENVUELTO' => $data['salida_envuelto'] ?? '',
        'INSTALACION DE OFFICE / FIRMWARE' => $data['salida_office'] ?? '',
        'PRUEBA DE VIDEO / IMPRESIÓN' => $data['salida_video'] ?? '',
        'PASO DE INFO / ACCESORIOS' => $data['salida_informacion'] ?? ''
    ];
    
    $observaciones_gen = $data['entrada_observaciones'] ?? '';
    $reparacion_realizada = $data['reparacion_realizada'] ?? '';
    

    
    $diagnostico = $data['diagnostico'] ?? '';
    $ing_repara = $data['ing_repara'] ?? '';
    $persona_recibe = $data['persona_recibe'] ?? '';

    $pdf = new MYPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    $logoPath = dirname(dirname(dirname(__FILE__))) . '/crud/img/logolargo.png';

    $dia = date('d');
    $mes = date('m');
    $anio = date('Y');
    $fecha = "$dia/$mes/$anio";

    $html = '
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Helvetica, Arial, sans-serif; }
        td, th { padding: 6px; font-size: 8.5pt; color: #333; }
        .header-main { background-color: #f8fafc; border-bottom: 2px solid #1e3a8a; padding: 15px 10px; }
        .section-header { background-color: #1e3a8a; color: #ffffff; font-weight: bold; text-align: left; padding: 6px 12px; font-size: 9.5pt; text-transform: uppercase; letter-spacing: 0.5px; }
        .field-label { font-weight: bold; color: #1e3a8a; font-size: 8pt; text-transform: uppercase; background-color: #f1f5f9; border-right: 1px solid #cbd5e1; }
        .field-value { border-bottom: 1px solid #e2e8f0; font-size: 9pt; padding-left: 8px; }
        .table-border td { border: 1px solid #cbd5e1; }
        .si-no-box { text-align: center; font-weight: bold; color: #1e3a8a; width: 35px; border: 1px solid #cbd5e1; }
        .terms-block { font-size: 7.2pt; color: #64748b; line-height: 1.4; text-align: justify; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        .signature-title { font-size: 8pt; font-weight: bold; color: #1e3a8a; margin-top: 5px; }
        .signature-sub { font-size: 7pt; color: #94a3b8; }
        .report-info { color: #1e3a8a; font-weight: bold; font-size: 10.5pt; }
    </style>
    
    <table border="0" cellpadding="0" cellspacing="0" class="header-main">
        <tr>
            <td width="60%" style="vertical-align: middle;">';
            if (file_exists($logoPath)) {
                $html .= '<img src="' . $logoPath . '" width="150">';
            } else {
                $html .= '<h1 style="color: #1e3a8a; margin: 0; font-size: 24pt;">SecuriTI</h1>';
            }
            $html .= '<div style="font-size: 7.5pt; color: #64748b; margin-top: 5px;">
                Servicios Especializados en Tecnologías de la Información<br>
                Soporte Técnico y Seguridad Informática
            </div>';
    $html .= '</td>
            <td width="40%" style="text-align: right; vertical-align: top;">
                <table border="0" style="width: 100%;">
                    <tr>
                        <td style="text-align: right; font-weight: bold; color: #64748b; text-transform: uppercase; font-size: 8pt;">No. Reporte:</td>
                        <td class="report-info" style="text-align: right; width: 100px;">' . $numeroReporte . '</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; font-weight: bold; color: #64748b; text-transform: uppercase; font-size: 8pt;">Fecha:</td>
                        <td style="text-align: right; color: #334155; font-size: 10pt;">' . $fecha . '</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <br><br>
    
    <div class="section-header">1. Información General del Equipo (' . $categoria . ')</div>
    <table class="table-border" style="width: 100%;">
        <tr>
            <td class="field-label" width="18%">Marca:</td>
            <td width="32%">' . (htmlspecialchars($marca) ?: 'N/A') . '</td>
            <td class="field-label" width="18%">Empresa:</td>
            <td width="32%">' . (htmlspecialchars($empresa) ?: 'N/A') . '</td>
        </tr>
        <tr>
            <td class="field-label">Modelo:</td>
            <td>' . (htmlspecialchars($modelo) ?: 'N/A') . '</td>
            <td class="field-label">' . ($categoria === 'Mantenimiento Preventivo' ? 'Usuario:' : 'Responsable:') . '</td>
            <td>' . (htmlspecialchars($responsable) ?: 'N/A') . '</td>
        </tr>
        <tr>
            <td class="field-label">N/S:</td>
            <td ' . ($categoria === 'Mantenimiento Preventivo' ? '' : 'colspan="3"') . '>' . (htmlspecialchars($serie) ?: 'N/A') . '</td>';
            if ($categoria !== 'Mantenimiento Preventivo') {
                $html .= '<td class="field-label">Contraseña:</td>
                <td>' . (htmlspecialchars($contrasena) ?: 'N/A') . '</td>';
            } else {
                $html .= '<td class="field-label">ID / Nombre:</td>
                <td>' . (htmlspecialchars($nombre_equipo) ?: 'N/A') . '</td>';
            }
        $html .= '</tr>';
        
        if ($categoria !== 'Mantenimiento Preventivo') {
            $html .= '<tr>
                <td colspan="4" style="padding: 10px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                    <strong>¿Autoriza Restauración de Fábrica?</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    [' . ($restauracion == 'SI' ? '<b>X</b>' : ' ') . '] SI &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; [' . ($restauracion == 'NO' ? '<b>X</b>' : ' ') . '] NO
                    <br><small style="color: #64748b; font-size: 7pt;">(Nota: La restauración de fábrica borra toda la información y software del equipo)</small>
                </td>
            </tr>';
        }
    $html .= '</table>
    
    <br><br>
    
    <div class="section-header">' . ($categoria === 'Mantenimiento Preventivo' ? '2. Aditamentos y Componentes' : '2. Aditamentos y Componentes') . '</div>
    <table class="table-border" style="width: 100%;">
        <tr>
            <td colspan="4" style="padding: 10px; border: 1px solid #cbd5e1;">';
            
            if ($categoria === 'Laptop') {
                $html .= '<span class="field-label" style="background:none; border:none;">Cargador:</span> [' . ($cargador == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($cargador == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Batería:</span> [' . ($bateria == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($bateria == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Cable AC:</span> [' . ($cable_ac_l == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($cable_ac_l == 'NO' ? '<b>X</b>' : ' ') . '] NO';
            } elseif ($categoria === 'Impresora') {
                $html .= '<span class="field-label" style="background:none; border:none;">Cable AC:</span> [' . ($cable_ac_imp == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($cable_ac_imp == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Cable USB:</span> [' . ($usb == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($usb == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Tonér:</span> [' . ($toner == 'SI' ? '<b>X</b>' : ' ') . '] SI &nbsp; / &nbsp;
                <span class="field-label" style="background:none; border:none;">Tinta:</span> [' . ($tinta == 'SI' ? '<b>X</b>' : ' ') . '] SI';
            } else {
                // PC o Mantenimiento Preventivo
                $html .= '<span class="field-label" style="background:none; border:none;">Monitor:</span> [' . ($monitor == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($monitor == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Teclado:</span> [' . ($teclado == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($teclado == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Mouse:</span> [' . ($mouse == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($mouse == 'NO' ? '<b>X</b>' : ' ') . '] NO <br>
                <span class="field-label" style="background:none; border:none;">All-in-one:</span> [' . ($aio == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($aio == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Cable AC:</span> [' . ($cable_ac_pc == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($cable_ac_pc == 'NO' ? '<b>X</b>' : ' ') . '] NO &nbsp;&nbsp;&nbsp;
                <span class="field-label" style="background:none; border:none;">Cable Video:</span> [' . ($cable_video == 'SI' ? '<b>X</b>' : ' ') . '] SI / [' . ($cable_video == 'NO' ? '<b>X</b>' : ' ') . '] NO';
                
                if ($categoria === 'Salida a Laboratorio') {
                    $html .= '&nbsp;&nbsp;&nbsp;<span class="field-label" style="background:none; border:none;">Impresora:</span> [' . ($adit_impresora == 'SI' ? '<b>X</b>' : ' ') . '] SI';
                }
            }
            
            $html .= '</td>
        </tr>';
        
        if ($categoria !== 'Mantenimiento Preventivo') {
            $html .= '<tr>
                <td class="field-label" width="20%">Disco Duro / Tambor:</td>
                <td width="30%">' . (htmlspecialchars($disco_duro) ?: 'N/A') . '</td>
                <td class="field-label" width="20%">RAM / Insumos:</td>
                <td width="30%">' . (htmlspecialchars($memoria_ram) ?: 'N/A') . '</td>
            </tr>
            <tr>
                <td class="field-label">Falla Reportada:</td>
                <td colspan="3">' . (htmlspecialchars($falla_reportada) ?: 'No especificada') . '</td>
            </tr>';
        }

        $html .= '<tr>
            <td class="field-label">Otros:</td>
            <td colspan="3">' . (htmlspecialchars($otros) ?: 'N/A') . '</td>
        </tr>
    </table>
    
    <br><br>    
    
    <div class="section-header">' . ($categoria === 'Mantenimiento Preventivo' ? 'Observaciones' : ($categoria === 'Salida a Laboratorio' ? 'Motivo de Traslado / Diagnóstico' : '3. Inspección Física y Diagnóstico')) . '</div>';
    
    if ($categoria === 'Mantenimiento Preventivo' || $categoria === 'Salida a Laboratorio') {
        $html .= '<div style="border: 1px solid #cbd5e1; height: ' . ($categoria === 'Salida a Laboratorio' ? '200px' : '320px') . '; padding: 12px; font-size: 10pt; line-height: 1.5; color: #1e293b; background-color: #ffffff;">' . 
        ($categoria === 'Salida a Laboratorio' ? (nl2br(htmlspecialchars($reparacion_realizada)) ?: 'Motivo de traslado no especificado.') : (nl2br(htmlspecialchars($observaciones_gen)) ?: 'Sin observaciones registradas.')) . '</div>';
        
        if ($categoria === 'Salida a Laboratorio') {
            $html .= '<br><div class="section-header">Revision de Equipo</div>
            <table class="table-border" style="width: 100%;">
                <tr style="background-color: #f1f5f9;">
                    <th width="70%" style="font-weight: bold; text-align: center;">Aspecto Evaluado</th>
                    <th width="15%" style="font-weight: bold; text-align: center;">SI</th>
                    <th width="15%" style="font-weight: bold; text-align: center;">NO</th>
                </tr>';
                foreach ($condiciones as $label => $val) {
                    $html .= '<tr>
                        <td style="font-size: 8pt;">' . $label . '</td>
                        <td style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($val == 'SI' ? 'X' : '') . '</td>
                        <td style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($val == 'NO' ? 'X' : '') . '</td>
                    </tr>';
                }
            $html .= '</table>';
        }
    } else {
        $html .= '<table class="table-border" style="width: 100%;">
            <tr style="background-color: #f1f5f9;">
                <th width="45%" style="font-weight: bold; text-align: center;">Aspecto Evaluado</th>
                <th width="8%" style="font-weight: bold; text-align: center;">SI</th>
                <th width="8%" style="font-weight: bold; text-align: center;">NO</th>
                <th width="39%" style="font-weight: bold; text-align: center;">Observaciones de Inspección</th>
            </tr>';
            
            $rowspan = count($condiciones);
            $first = true;
            foreach ($condiciones as $label => $val) {
                $html .= '<tr>
                    <td style="font-size: 7.5pt;">' . $label . '</td>
                    <td style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($val == 'SI' ? 'X' : '') . '</td>
                    <td style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($val == 'NO' ? 'X' : '') . '</td>';
                if ($first) {
                    $html .= '<td rowspan="' . $rowspan . '" style="vertical-align: top; font-size: 8pt; padding: 10px;">' . (nl2br(htmlspecialchars($observaciones_gen)) ?: 'Sin detalles adicionales.') . '</td>';
                    $first = false;
                }
                $html .= '</tr>';
            }
        $html .= '</table>
        
        <br><br>
        
        <table border="0" style="width: 100%;">
            <tr>
                <td width="65%" style="vertical-align: top; padding: 0;">
                    <div class="section-header" style="background-color: #334155;">4. Descripción del Servicio Técnico</div>
                    <div style="border: 1px solid #cbd5e1; height: 160px; padding: 12px; font-size: 9pt; line-height: 1.5; color: #1e293b; background-color: #ffffff;">' . (nl2br(htmlspecialchars($reparacion_realizada)) ?: 'Pendiente de ejecución.') . '</div>
                </td>
                <td width="2%"></td>
                <td width="33%" style="vertical-align: top; padding: 0;">';
                    
                    if ($categoria === 'Impresora') {
                        $html .= '<div class="section-header" style="background-color: #334155;">Pruebas Gen.</div>
                        <table class="table-border" style="width: 100%;">
                            <tr><td style="font-size: 6.8pt; color: #475569;" width="75%">Enciende AC:</td><td width="25%" style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($test_enciende == 'SI' ? 'OK' : '-') . '</td></tr>
                            <tr><td style="font-size: 6.8pt; color: #475569;">P. Escaneo:</td><td style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($test_escaneo == 'SI' ? 'OK' : '-') . '</td></tr>
                            <tr><td style="font-size: 6.8pt; color: #475569;">P. Copiado:</td><td style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($test_copiado == 'SI' ? 'OK' : '-') . '</td></tr>
                            <tr><td style="font-size: 6.8pt; color: #475569;">Act. Firmw:</td><td style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($test_firmware == 'SI' ? 'OK' : '-') . '</td></tr>
                        </table><div style="margin: 5px 0;"></div>';
                    }

                    $html .= '<br>
                    <div class="section-header" style="background-color: #1e3a8a;">Checklist de Salida</div>
                    <table class="table-border" style="width: 100%;">';
                    foreach ($salida as $label => $val) {
                        $html .= '<tr>
                            <td style="font-size: 6.8pt; color: #475569;" width="75%">' . $label . '</td>
                            <td width="25%" style="text-align: center; font-weight: bold; color: #1e3a8a;">' . ($val == 'SI' ? 'OK' : '-') . '</td>
                        </tr>';
                    }
        $html .= '</table>
                </td>
            </tr>
        </table>
        
        <br><br><br>
        
        <table class="table-border" style="width: 100%;">
            <tr>
                <td class="field-label" width="20%">Diagnóstico:</td>
                <td width="30%">' . (htmlspecialchars($diagnostico) ?: 'N/A') . '</td>
                <td class="field-label" width="20%">Técnico Asignado:</td>
                <td width="30%">' . (htmlspecialchars($ing_repara) ?: 'N/A') . '</td>
            </tr>
        </table>';
    }

    $html .= '
    <br><br><br><br><br>
    <table border="0" style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="text-align: center; width: 45%;">
                <div style="border-top: 1px solid #1e3a8a; width: 220px; margin: 0 auto;"></div>
                <span class="signature-title">' . ($categoria === 'Salida a Laboratorio' ? (htmlspecialchars($responsable) ?: 'PERSONA QUE AUTORIZA') : (htmlspecialchars($persona_recibe) ?: 'NOMBRE Y FIRMA CLIENTE')) . '</span><br>
                <span class="signature-sub">' . ($categoria === 'Salida a Laboratorio' ? 'Persona que Autoriza' : 'Conformidad de Recepción de Equipo') . '</span>
            </td>
            <td width="10%"></td>
            <td style="text-align: center; width: 45%;">
                <div style="border-top: 1px solid #1e3a8a; width: 220px; margin: 0 auto;"></div>
                <span class="signature-title">' . ($categoria === 'Salida a Laboratorio' ? 'ING. QUE TRANSPORTA' : 'DEPARTAMENTO TÉCNICO') . '</span><br>
                <span class="signature-sub">' . ($categoria === 'Salida a Laboratorio' ? 'Ing. que Transporta' : 'Sello y Firma de Autorización') . '</span>
            </td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    $pdf->Output('carta_mantenimiento_' . $categoria . '_' . $empresa . '.pdf', 'I');
    exit;
} catch (Exception $e) {
    echo 'Error al generar el PDF: ' . $e->getMessage();
}
?>
