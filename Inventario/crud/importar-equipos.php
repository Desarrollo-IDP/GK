<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../includes/empresa_helper.php';
require_once __DIR__ . '/../../log_activity.php';
require_once __DIR__ . '/../includes/equipment_counter_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    if ($_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo']['tmp_name'];
        $mimeType = mime_content_type($archivo);
        $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        
        if ($mimeType === 'text/csv' || $mimeType === 'application/vnd.ms-excel') {
            
            // Función para detectar y convertir codificación
            function detectAndConvertEncoding($text) {
                // Detectar codificación
                $detected = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
                
                // Si no es UTF-8, convertir
                if ($detected && $detected !== 'UTF-8') {
                    $text = mb_convert_encoding($text, 'UTF-8', $detected);
                } elseif (!$detected) {
                    // Si no se detecta, intentar convertir desde Windows-1252 (común en Excel)
                    $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
                }
                
                return $text;
            }
            
            // Leer el archivo completo para detectar codificación
            $file_content = file_get_contents($archivo);
            $detected_encoding = mb_detect_encoding($file_content, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
            
            // Si el archivo no está en UTF-8, convertirlo
            if ($detected_encoding && $detected_encoding !== 'UTF-8') {
                $file_content = mb_convert_encoding($file_content, 'UTF-8', $detected_encoding);
                // Guardar temporalmente el archivo convertido
                $temp_file = tempnam(sys_get_temp_dir(), 'csv_utf8_');
                file_put_contents($temp_file, $file_content);
                $archivo = $temp_file;
            }
            
            // Configurar locale para manejar caracteres especiales
            setlocale(LC_ALL, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain.1252');
            
            if (($handle = fopen($archivo, "r")) !== FALSE) {
                // Configurar el stream para UTF-8
                stream_filter_append($handle, 'convert.iconv.UTF-8/UTF-8', STREAM_FILTER_READ);
                
                // Leer primera línea (header) con manejo de codificación
                $header = fgetcsv($handle, 0, ',', '"', '\\');
                if ($header !== FALSE) {
                    $header = array_map('detectAndConvertEncoding', $header);
                }
                
                // Asegurar que la conexión use UTF-8
                $conn->set_charset("utf8mb4");
                
                // Fase 1: leer todas las filas del CSV
                $filas = [];
                while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
                    $data = array_map('detectAndConvertEncoding', $data);
                    $filas[] = $data;
                }
                
                // Verificaciones de columnas/tablas (una sola vez)
                $check_column_equipos = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
                $has_empresa_usuario_column_equipos = $check_column_equipos && $check_column_equipos->num_rows > 0;
                $check_column_equipo_number = $conn->query("SHOW COLUMNS FROM equipos LIKE 'equipo_number'");
                $has_equipo_number_column = $check_column_equipo_number && $check_column_equipo_number->num_rows > 0;
                $check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
                $has_empresa_usuario_column_empresas = $check_column_empresas && $check_column_empresas->num_rows > 0;
                $tabla_ubicaciones_existe = $conn->query("SHOW TABLES LIKE 'inventario_config_ubicaciones'");
                $tabla_activos_existe = $conn->query("SHOW TABLES LIKE 'inventario_config_activos'");
                
                // Fase 2: validar TODAS las filas (sin insertar). Si hay cualquier error, no se importa nada.
                $filas_validas = [];
                foreach ($filas as $data) {
                    $nombre_cliente = trim($data[0]);
                    $stmt = $conn->prepare("SELECT id_empresa FROM empresas_equipos WHERE BINARY nombre = ?");
                    $stmt->bind_param("s", $nombre_cliente);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        $errors[] = "Cliente no encontrado en la base de datos: " . $nombre_cliente;
                        $stmt->close();
                        continue;
                    }
                    $empresa = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($user_empresa_id !== null && $has_empresa_usuario_column_empresas) {
                        $check_empresa_stmt = $conn->prepare("SELECT id_empresa, id_empresa_usuario FROM empresas_equipos WHERE id_empresa = ?");
                        $check_empresa_stmt->bind_param("i", $empresa['id_empresa']);
                        $check_empresa_stmt->execute();
                        $check_empresa_result = $check_empresa_stmt->get_result();
                        if ($check_empresa_result->num_rows > 0) {
                            $empresa_data = $check_empresa_result->fetch_assoc();
                            $empresa_id_usuario = $empresa_data['id_empresa_usuario'];
                            if ($empresa_id_usuario !== null && $empresa_id_usuario != $user_empresa_id) {
                                $errors[] = "No tienes permiso para importar equipos para la empresa: " . $nombre_cliente . " (la empresa está asignada a otra empresa de usuario)";
                                $check_empresa_stmt->close();
                                continue;
                            }
                        }
                        $check_empresa_stmt->close();
                    }
                    
                    if ($user_empresa_id !== null) {
                        $ubicacion_val = trim(detectAndConvertEncoding($data[13] ?? ''));
                        $activo_val = trim(detectAndConvertEncoding($data[14] ?? ''));
                        $nombre_equipo_fila = trim($data[1] ?? '');
                        if ($tabla_ubicaciones_existe && $tabla_ubicaciones_existe->num_rows > 0 && $ubicacion_val !== '') {
                            $v_ubic = $conn->prepare("SELECT 1 FROM inventario_config_ubicaciones WHERE id_empresa_usuario = ? AND valor = ? LIMIT 1");
                            $v_ubic->bind_param("is", $user_empresa_id, $ubicacion_val);
                            $v_ubic->execute();
                            $r_ubic = $v_ubic->get_result();
                            if ($r_ubic->num_rows === 0) {
                                $errors[] = "Fila no importada (empresa: " . $nombre_cliente . ", equipo: " . $nombre_equipo_fila . "): La ubicación \"" . $ubicacion_val . "\" no está registrada en la configuración de tu empresa. Agregue la opción en Configuración o deje el campo vacío en el CSV.";
                                $r_ubic->free();
                                $v_ubic->close();
                                continue;
                            }
                            $r_ubic->free();
                            $v_ubic->close();
                        }
                        if ($tabla_activos_existe && $tabla_activos_existe->num_rows > 0 && $activo_val !== '') {
                            $v_act = $conn->prepare("SELECT 1 FROM inventario_config_activos WHERE id_empresa_usuario = ? AND valor = ? LIMIT 1");
                            $v_act->bind_param("is", $user_empresa_id, $activo_val);
                            $v_act->execute();
                            $r_act = $v_act->get_result();
                            if ($r_act->num_rows === 0) {
                                $errors[] = "Fila no importada (empresa: " . $nombre_cliente . ", equipo: " . $nombre_equipo_fila . "): El activo del equipo \"" . $activo_val . "\" no está registrado en la configuración de tu empresa. Agregue la opción en Configuración o deje el campo vacío en el CSV.";
                                $r_act->free();
                                $v_act->close();
                                continue;
                            }
                            $r_act->free();
                            $v_act->close();
                        }
                    }
                    
                    $filas_validas[] = ['data' => $data, 'empresa' => $empresa];
                }
                
                // Solo si no hay errores de validación: abrir transacción e insertar
                if (!empty($errors)) {
                    $message = "No se importó ningún equipo. Corrija los errores e intente de nuevo.";
                } else {
                    $conn->begin_transaction();
                    try {
                        $usuario_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0;
                        foreach ($filas_validas as $item) {
                            $data = $item['data'];
                            $empresa = $item['empresa'];
                            $nombre_cliente = trim($data[0]);
                            
                            if ($has_empresa_usuario_column_equipos && $user_empresa_id !== null) {
                                // Obtener el siguiente número de equipo para la empresa
                                $equipo_number = getNextEquipmentNumber($conn, $user_empresa_id);
                                
                                // Verificar si existe columna equipo_number
                                $check_column_equipo_number = $conn->query("SHOW COLUMNS FROM equipos LIKE 'equipo_number'");
                                $has_equipo_number_column = $check_column_equipo_number && $check_column_equipo_number->num_rows > 0;
                                
                                if ($has_equipo_number_column) {
                                    // Insertar con id_empresa_usuario y equipo_number
                                    $stmt = $conn->prepare("INSERT INTO equipos (
                                        nombre_cliente, nombre_equipo, responsable, sistema_operativo, 
                                        direccion_mac, tipo, fabricante, modelo, numero_serie, 
                                        ram, disco_duro, procesador, observaciones, ubicacion, activo_equipo, id_empresa, id_empresa_usuario, equipo_number
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                    
                                    // Limpiar y convertir cada campo a UTF-8
                                    $nombre_cliente_utf8 = detectAndConvertEncoding(trim($nombre_cliente));
                                    $nombre_equipo_utf8 = detectAndConvertEncoding(trim($data[1] ?? ''));
                                    $responsable_utf8 = detectAndConvertEncoding(trim($data[2] ?? ''));
                                    $sistema_operativo_utf8 = detectAndConvertEncoding(trim($data[3] ?? ''));
                                    $direccion_mac_utf8 = detectAndConvertEncoding(trim($data[4] ?? ''));
                                    $tipo_utf8 = detectAndConvertEncoding(trim($data[5] ?? ''));
                                    $fabricante_utf8 = detectAndConvertEncoding(trim($data[6] ?? ''));
                                    $modelo_utf8 = detectAndConvertEncoding(trim($data[7] ?? ''));
                                    $numero_serie_utf8 = detectAndConvertEncoding(trim($data[8] ?? ''));
                                    $ram_utf8 = detectAndConvertEncoding(trim($data[9] ?? ''));
                                    $disco_duro_utf8 = detectAndConvertEncoding(trim($data[10] ?? ''));
                                    $procesador_utf8 = detectAndConvertEncoding(trim($data[11] ?? ''));
                                    $observaciones_utf8 = detectAndConvertEncoding(trim($data[12] ?? ''));
                                    $ubicacion_utf8 = detectAndConvertEncoding(trim($data[13] ?? ''));
                                    $activo_equipo_utf8 = detectAndConvertEncoding(trim($data[14] ?? ''));
                                    
                                    $stmt->bind_param("sssssssssssssssiis", 
                                        $nombre_cliente_utf8,
                                        $nombre_equipo_utf8,
                                        $responsable_utf8,
                                        $sistema_operativo_utf8,
                                        $direccion_mac_utf8,
                                        $tipo_utf8,
                                        $fabricante_utf8,
                                        $modelo_utf8,
                                        $numero_serie_utf8,
                                        $ram_utf8,
                                        $disco_duro_utf8,
                                        $procesador_utf8,
                                        $observaciones_utf8,
                                        $ubicacion_utf8,
                                        $activo_equipo_utf8,
                                        $empresa['id_empresa'],
                                        $user_empresa_id,
                                        $equipo_number
                                    );
                                } else {
                                    // Insertar con id_empresa_usuario sin equipo_number (campo no existe aún)
                                    $stmt = $conn->prepare("INSERT INTO equipos (
                                        nombre_cliente, nombre_equipo, responsable, sistema_operativo, 
                                        direccion_mac, tipo, fabricante, modelo, numero_serie, 
                                        ram, disco_duro, procesador, observaciones, ubicacion, activo_equipo, id_empresa, id_empresa_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                    
                                    // Limpiar y convertir cada campo a UTF-8
                                    $nombre_cliente_utf8 = detectAndConvertEncoding(trim($nombre_cliente));
                                    $nombre_equipo_utf8 = detectAndConvertEncoding(trim($data[1] ?? ''));
                                    $responsable_utf8 = detectAndConvertEncoding(trim($data[2] ?? ''));
                                    $sistema_operativo_utf8 = detectAndConvertEncoding(trim($data[3] ?? ''));
                                    $direccion_mac_utf8 = detectAndConvertEncoding(trim($data[4] ?? ''));
                                    $tipo_utf8 = detectAndConvertEncoding(trim($data[5] ?? ''));
                                    $fabricante_utf8 = detectAndConvertEncoding(trim($data[6] ?? ''));
                                    $modelo_utf8 = detectAndConvertEncoding(trim($data[7] ?? ''));
                                    $numero_serie_utf8 = detectAndConvertEncoding(trim($data[8] ?? ''));
                                    $ram_utf8 = detectAndConvertEncoding(trim($data[9] ?? ''));
                                    $disco_duro_utf8 = detectAndConvertEncoding(trim($data[10] ?? ''));
                                    $procesador_utf8 = detectAndConvertEncoding(trim($data[11] ?? ''));
                                    $observaciones_utf8 = detectAndConvertEncoding(trim($data[12] ?? ''));
                                    $ubicacion_utf8 = detectAndConvertEncoding(trim($data[13] ?? ''));
                                    $activo_equipo_utf8 = detectAndConvertEncoding(trim($data[14] ?? ''));
                                    
                                    $stmt->bind_param("sssssssssssssssii", 
                                        $nombre_cliente_utf8,
                                        $nombre_equipo_utf8,
                                        $responsable_utf8,
                                        $sistema_operativo_utf8,
                                        $direccion_mac_utf8,
                                        $tipo_utf8,
                                        $fabricante_utf8,
                                        $modelo_utf8,
                                        $numero_serie_utf8,
                                        $ram_utf8,
                                        $disco_duro_utf8,
                                        $procesador_utf8,
                                        $observaciones_utf8,
                                        $ubicacion_utf8,
                                        $activo_equipo_utf8,
                                        $empresa['id_empresa'],
                                        $user_empresa_id
                                    );
                                }
                            } else {
                                // Insertar sin id_empresa_usuario (campo no existe aún)
                            $stmt = $conn->prepare("INSERT INTO equipos (
                                nombre_cliente, nombre_equipo, responsable, sistema_operativo, 
                                direccion_mac, tipo, fabricante, modelo, numero_serie, 
                                ram, disco_duro, procesador, observaciones, ubicacion, activo_equipo, id_empresa
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            
                            // Limpiar y convertir cada campo a UTF-8
                            $nombre_cliente_utf8 = detectAndConvertEncoding(trim($nombre_cliente));
                            $nombre_equipo_utf8 = detectAndConvertEncoding(trim($data[1] ?? ''));
                            $responsable_utf8 = detectAndConvertEncoding(trim($data[2] ?? ''));
                            $sistema_operativo_utf8 = detectAndConvertEncoding(trim($data[3] ?? ''));
                            $direccion_mac_utf8 = detectAndConvertEncoding(trim($data[4] ?? ''));
                            $tipo_utf8 = detectAndConvertEncoding(trim($data[5] ?? ''));
                            $fabricante_utf8 = detectAndConvertEncoding(trim($data[6] ?? ''));
                            $modelo_utf8 = detectAndConvertEncoding(trim($data[7] ?? ''));
                            $numero_serie_utf8 = detectAndConvertEncoding(trim($data[8] ?? ''));
                            $ram_utf8 = detectAndConvertEncoding(trim($data[9] ?? ''));
                            $disco_duro_utf8 = detectAndConvertEncoding(trim($data[10] ?? ''));
                            $procesador_utf8 = detectAndConvertEncoding(trim($data[11] ?? ''));
                            $observaciones_utf8 = detectAndConvertEncoding(trim($data[12] ?? ''));
                            $ubicacion_utf8 = detectAndConvertEncoding(trim($data[13] ?? ''));
                            $activo_equipo_utf8 = detectAndConvertEncoding(trim($data[14] ?? ''));
                            
                            $stmt->bind_param("sssssssssssssssi", 
                                $nombre_cliente_utf8,
                                $nombre_equipo_utf8,
                                $responsable_utf8,
                                $sistema_operativo_utf8,
                                $direccion_mac_utf8,
                                $tipo_utf8,
                                $fabricante_utf8,
                                $modelo_utf8,
                                $numero_serie_utf8,
                                $ram_utf8,
                                $disco_duro_utf8,
                                $procesador_utf8,
                                $observaciones_utf8,
                                $ubicacion_utf8,
                                $activo_equipo_utf8,
                                $empresa['id_empresa'] 
                            );
                            }
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Error al importar equipo para cliente: " . $nombre_cliente . ". " . $stmt->error);
                            }
                            $equipo_id = $conn->insert_id;
                            $nombre_usuario = isset($_SESSION['user']['nombre']) ? $_SESSION['user']['nombre'] : 
                                            (isset($_SESSION['user']['username']) ? $_SESSION['user']['username'] : 'sistema');
                            
                            
                            require_once '../../log_activity.php';
                            logActivity(
                                "Importación de equipo",
                                "equipos",
                                $equipo_id,
                                "Se importó el equipo: " . $data[1] . " - De la empresa: " . $nombre_cliente
                            );
                        }
                        $conn->commit();
                        $message = "Importación completada exitosamente.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error: " . $e->getMessage();
                    }
                }
                
                fclose($handle);
                
                // Limpiar archivo temporal si se creó
                if (isset($temp_file) && file_exists($temp_file)) {
                    @unlink($temp_file);
                }
            }
        } elseif ($extension === 'html' || $mimeType === 'text/html') {
            // Procesar archivo HTML WinAudit
            $html = file_get_contents($archivo);
            
            // Detectar y convertir codificación del HTML
            $detected_encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
            if ($detected_encoding && $detected_encoding !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $detected_encoding);
            } elseif (!$detected_encoding) {
                // Si no se detecta, intentar convertir desde Windows-1252
                $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
            }
            
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            // Especificar codificación UTF-8 explícitamente
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            
            // Extraer datos de la tabla 'Vista General'
            $data = [
                'nombre_equipo' => '',
                'sistema_operativo' => '',
                'direccion_mac' => '',
                'fabricante' => '',
                'modelo' => '',
                'numero_serie' => '',
                'ram' => '',
                'disco_duro' => '',
                'procesador' => '',
                'ubicacion' => '',
                'activo_equipo' => '',
            ];
            // 1. Buscar tabla de Vista General por texto
            $vistaGeneralTable = null;
            $vistaGeneralNodes = $xpath->query('//center/b[contains(text(),"Vista General")]');
            if ($vistaGeneralNodes->length > 0) {
                $bNode = $vistaGeneralNodes->item(0);
                // Buscar la siguiente tabla en el DOM (no solo hermanos directos)
                $next = $bNode->parentNode;
                while ($next && $next = $next->nextSibling) {
                    if ($next->nodeType === XML_ELEMENT_NODE && $next->nodeName === 'table') {
                        $vistaGeneralTable = $next;
                        break;
                    }
                }
            }
            // Si no se encontró, buscar tabla que contenga 'Computer Name'
            if (!$vistaGeneralTable) {
                $tables = $dom->getElementsByTagName('table');
                foreach ($tables as $table) {
                    foreach ($table->getElementsByTagName('tr') as $row) {
                        $cols = $row->getElementsByTagName('td');
                        if ($cols->length == 2 && trim($cols->item(0)->textContent) === 'Computer Name') {
                            $vistaGeneralTable = $table;
                            break 2;
                        }
                    }
                }
            }
            if ($vistaGeneralTable) {
                $rows = $vistaGeneralTable->getElementsByTagName('tr');
                foreach ($rows as $row) {
                    $cols = $row->getElementsByTagName('td');
                    if ($cols->length == 2) {
                        $item = trim($cols->item(0)->textContent);
                        $value = trim($cols->item(1)->textContent);
                        switch ($item) {
                            case 'Computer Name': $data['nombre_equipo'] = $value; break;
                            case 'Operating System': $data['sistema_operativo'] = $value; break;
                            case 'Manufacturer': $data['fabricante'] = $value; break;
                            case 'Model': $data['modelo'] = $value; break;
                            case 'Serial Number': $data['numero_serie'] = $value; break;
                            case 'Total Memory': $data['ram'] = $value; break;
                            case 'Total Hard Drive': $data['disco_duro'] = $value; break;
                            case 'Processor Description': $data['procesador'] = $value; break;
                        }
                    }
                }
            }
            // 2. Buscar todas las MAC Address y nombres de adaptador después de '425) Network Adapters'
            $macs = [];
            // Buscar el nodo de '425) Network Adapters'
            $networkAdaptersNodes = $xpath->query('//a[contains(text(),"425) Network Adapters")]');
            if ($networkAdaptersNodes->length > 0) {
                $aNode = $networkAdaptersNodes->item(0);
                $current = $aNode->parentNode;
                // Recorrer los siguientes nodos buscando tablas de adaptadores
                while ($current && $current = $current->nextSibling) {
                    if ($current->nodeType === XML_ELEMENT_NODE && $current->nodeName === 'b') {
                        // Si encontramos otro <b> (otro adaptador), seguimos
                        $adapterName = trim($current->textContent);
                        // Buscar la siguiente tabla
                        $table = null;
                        $next = $current;
                        while ($next && $next = $next->nextSibling) {
                            if ($next->nodeType === XML_ELEMENT_NODE && $next->nodeName === 'table') {
                                $table = $next;
                                break;
                            }
                        }
                        if ($table) {
                            $rows = $table->getElementsByTagName('tr');
                            $macValue = '';
                            foreach ($rows as $row) {
                                $cols = $row->getElementsByTagName('td');
                                if ($cols->length == 2) {
                                    $item = trim($cols->item(0)->textContent);
                                    $value = trim($cols->item(1)->textContent);
                                    if ($item === 'MAC Address' && $value !== '') {
                                        $macValue = $value;
                                        break;
                                    }
                                }
                            }
                            if ($macValue !== '') {
                                $macs[] = $adapterName . ': ' . $macValue;
                            }
                        }
                    }
                    // Si llegamos a otra sección importante, paramos
                    if ($current->nodeType === XML_ELEMENT_NODE && in_array($current->nodeName, ['center', 'a'])) {
                        break;
                    }
                }
            }
            // Si no se encontró por el método anterior, buscar todas las tablas que tengan 'Adapter Name' y 'MAC Address'
            if (empty($macs)) {
                $tables = $dom->getElementsByTagName('table');
                foreach ($tables as $table) {
                    $adapterName = '';
                    $macValue = '';
                    foreach ($table->getElementsByTagName('tr') as $row) {
                        $cols = $row->getElementsByTagName('td');
                        if ($cols->length == 2) {
                            $item = trim($cols->item(0)->textContent);
                            $value = trim($cols->item(1)->textContent);
                            if ($item === 'Adapter Name') {
                                $adapterName = $value;
                            }
                            if ($item === 'MAC Address' && $value !== '') {
                                $macValue = $value;
                            }
                        }
                    }
                    if ($adapterName !== '' && $macValue !== '') {
                        $macs[] = $adapterName . ': ' . $macValue;
                    }
                }
            }
            $data['direccion_mac'] = implode('; ', $macs);
            // DEBUG: Mostrar los valores extraídos (puedes quitar esto después de probar)
            // echo '<pre>' . print_r($data, true) . '</pre>'; exit;
            // Guardar datos en sesión temporal para el siguiente paso
            $_SESSION['import_html_data'] = $data;
            // Redirigir a formulario de confirmación
            header('Location: importar-equipos.php?confirm_html=1');
            exit;
        } else {
            $message = "Por favor, sube un archivo CSV o HTML válido.";
        }
    } else {
        $message = "Error al subir el archivo.";
    }
}

if (isset($_GET['confirm_html']) && isset($_SESSION['import_html_data'])) {
    // Mostrar formulario de confirmación/edición
    $data = $_SESSION['import_html_data'];
    // Obtener empresas (solo las del usuario si tiene id_empresa_usuario)
    $empresas = [];
    $check_column_empresas_confirm = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column_confirm = $check_column_empresas_confirm && $check_column_empresas_confirm->num_rows > 0;
    if ($has_empresa_usuario_column_confirm && $user_empresa_id !== null) {
        $empresas_stmt = $conn->prepare("SELECT id_empresa, nombre FROM empresas_equipos WHERE id_empresa_usuario = ? ORDER BY nombre");
        $empresas_stmt->bind_param("i", $user_empresa_id);
        $empresas_stmt->execute();
        $empresas_result = $empresas_stmt->get_result();
        while ($row = $empresas_result->fetch_assoc()) {
            $empresas[] = $row;
        }
        $empresas_result->free();
        $empresas_stmt->close();
    } else {
        $empresas_result = $conn->query("SELECT id_empresa, nombre FROM empresas_equipos ORDER BY nombre");
        while ($row = $empresas_result->fetch_assoc()) {
            $empresas[] = $row;
        }
    }
    // Opciones de Ubicación y Activo (según empresa del usuario)
    $opciones_ubicacion = [];
    $opciones_activo = [];
    $tabla_ubicaciones = $conn->query("SHOW TABLES LIKE 'inventario_config_ubicaciones'");
    $tabla_activos = $conn->query("SHOW TABLES LIKE 'inventario_config_activos'");
    if ($tabla_ubicaciones && $tabla_ubicaciones->num_rows > 0 && $tabla_activos && $tabla_activos->num_rows > 0 && $user_empresa_id !== null) {
        $st = $conn->prepare("SELECT valor FROM inventario_config_ubicaciones WHERE id_empresa_usuario = ? ORDER BY valor");
        $st->bind_param("i", $user_empresa_id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $opciones_ubicacion[] = $r['valor'];
        }
        $res->free();
        $st->close();
        $st = $conn->prepare("SELECT valor FROM inventario_config_activos WHERE id_empresa_usuario = ? ORDER BY valor");
        $st->bind_param("i", $user_empresa_id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $opciones_activo[] = $r['valor'];
        }
        $res->free();
        $st->close();
    }
    // Incluir header visual aquí
    require_once 'includes/header.php';
    ?>
    <div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
        <div class="inicio-header mb-8">
            <div class="flex items-center gap-3 mb-1">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                    <i class="fas fa-file-import text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Confirmar datos extraídos</h1>
                    <p class="inicio-header-subtitle">Revisa y ajusta la información del equipo antes de guardarlo.</p>
                </div>
            </div>
            
        </div>
        <form method="POST" enctype="multipart/form-data" action="importar-equipos.php?save_html=1" class="inv-form-card bg-white rounded-2xl shadow-xl border border-gray-100 p-6 lg:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-building w-4 mr-1 text-gray-400"></i> Empresa</label>
                    <select name="id_empresa" id="select_empresa_import" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
                        <option value="">Seleccione una empresa</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa['id_empresa']; ?>"><?php echo htmlspecialchars($empresa['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-desktop w-4 mr-1 text-gray-400"></i> Nombre del equipo</label>
                    <input type="text" name="nombre_equipo" value="<?php echo htmlspecialchars($data['nombre_equipo']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-user w-4 mr-1 text-gray-400"></i> Responsable</label>
                    <input type="text" name="responsable" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-laptop w-4 mr-1 text-gray-400"></i> Sistema operativo</label>
                    <input type="text" name="sistema_operativo" value="<?php echo htmlspecialchars($data['sistema_operativo']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-network-wired w-4 mr-1 text-gray-400"></i> Dirección MAC</label>
                    <input type="text" name="direccion_mac" value="<?php echo htmlspecialchars($data['direccion_mac']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none font-mono text-sm" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-tag w-4 mr-1 text-gray-400"></i> Tipo</label>
                    <input type="text" name="tipo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-industry w-4 mr-1 text-gray-400"></i> Fabricante</label>
                    <input type="text" name="fabricante" value="<?php echo htmlspecialchars($data['fabricante']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-cube w-4 mr-1 text-gray-400"></i> Modelo</label>
                    <input type="text" name="modelo" value="<?php echo htmlspecialchars($data['modelo']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-barcode w-4 mr-1 text-gray-400"></i> Número de serie</label>
                    <input type="text" name="numero_serie" value="<?php echo htmlspecialchars($data['numero_serie']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none font-mono text-sm">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-memory w-4 mr-1 text-gray-400"></i> RAM</label>
                    <input type="text" name="ram" value="<?php echo htmlspecialchars($data['ram']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-hdd w-4 mr-1 text-gray-400"></i> Disco duro</label>
                    <input type="text" name="disco_duro" value="<?php echo htmlspecialchars($data['disco_duro']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-microchip w-4 mr-1 text-gray-400"></i> Procesador</label>
                    <input type="text" name="procesador" value="<?php echo htmlspecialchars($data['procesador']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-comment-alt w-4 mr-1 text-gray-400"></i> Observaciones</label>
                <textarea name="observaciones" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none resize-y"></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-map-marker-alt w-4 mr-1 text-gray-400"></i> Ubicación</label>
                    <select name="ubicacion" id="select_ubicacion_import" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                        <option value="">Seleccione ubicación</option>
                        <?php
                        $val_ubicacion = $data['ubicacion'] ?? '';
                        foreach ($opciones_ubicacion as $opt):
                            $sel = ($val_ubicacion !== '' && $opt === $val_ubicacion) ? ' selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($opt); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach;
                        if ($val_ubicacion !== '' && !in_array($val_ubicacion, $opciones_ubicacion, true)) {
                            echo '<option value="' . htmlspecialchars($val_ubicacion) . '" selected>' . htmlspecialchars($val_ubicacion) . '</option>';
                        }
                        ?>
                    </select>
                    
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-check-circle w-4 mr-1 text-gray-400"></i> Activo del equipo</label>
                    <select name="activo_equipo" id="select_activo_import" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                        <option value="">Seleccione activo</option>
                        <?php
                        $val_activo = $data['activo_equipo'] ?? '';
                        foreach ($opciones_activo as $opt):
                            $sel = ($val_activo !== '' && $opt === $val_activo) ? ' selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($opt); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach;
                        if ($val_activo !== '' && !in_array($val_activo, $opciones_activo, true)) {
                            echo '<option value="' . htmlspecialchars($val_activo) . '" selected>' . htmlspecialchars($val_activo) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-600 mb-2"><i class="fas fa-file-alt w-4 mr-1 text-gray-400"></i> Documentos (PDF, JPG, PNG, Excel, Word)</label>
                <input type="file" name="documentos[]" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-medium file:inv-brand-bg file:text-white file:cursor-pointer" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx" onchange="mostrarArchivosSeleccionados(this)">
                <p class="text-sm text-gray-500 mt-1">Puedes seleccionar múltiples archivos.</p>
                <ul id="lista-archivos" class="list-disc pl-5 mt-2"></ul>
            </div>
            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-save"></i> Guardar equipo
                </button>
                <a href="importar-equipos.php" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
    <style>
    .inv-page-wrap { max-width: 1600px; }
    .inv-brand-bg { background-color: #1E2A3B; }
    .inv-form-card .inv-input { min-height: 42px; }
    .inv-form-card .inv-input:hover { border-color: #cbd5e1; }
    .inv-btn-primary:hover { filter: brightness(1.08); }
    .inicio-header {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .inicio-header-subtitle {
        margin: 0.25rem 0 0;
        font-size: 0.9rem;
        color: #6b7280;
    }
    .inicio-header-date {
        color: #6b7280;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .inicio-header-date i {
        color: #1E2A3B;
    }
    @media (max-width: 768px) {
        .inicio-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
    }
    </style>
    <script>
        function mostrarArchivosSeleccionados(input) {
            var lista = document.getElementById('lista-archivos');
            lista.innerHTML = '';
            if (input.files && input.files.length > 0) {
                for (var i = 0; i < input.files.length; i++) {
                    var li = document.createElement('li');
                    li.textContent = input.files[i].name;
                    lista.appendChild(li);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            var selEmpresa = document.getElementById('select_empresa_import');
            var selUbicacion = document.getElementById('select_ubicacion_import');
            var selActivo = document.getElementById('select_activo_import');
            if (!selEmpresa || !selUbicacion || !selActivo) return;
            function llenarOpciones(empresaId) {
                if (!empresaId) {
                    selUbicacion.innerHTML = '<option value="">Seleccione ubicación</option>';
                    selActivo.innerHTML = '<option value="">Seleccione activo</option>';
                    return;
                }
                fetch('api/opciones-config.php?empresa=' + encodeURIComponent(empresaId))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var ubActual = selUbicacion.value;
                        var actActual = selActivo.value;
                        selUbicacion.innerHTML = '<option value="">Seleccione ubicación</option>';
                        (data.ubicaciones || []).forEach(function(v) {
                            var o = document.createElement('option');
                            o.value = v;
                            o.textContent = v;
                            if (v === ubActual) o.selected = true;
                            selUbicacion.appendChild(o);
                        });
                        if (ubActual && !(data.ubicaciones || []).some(function(v) { return v === ubActual; })) {
                            var o = document.createElement('option');
                            o.value = ubActual;
                            o.textContent = ubActual;
                            o.selected = true;
                            selUbicacion.appendChild(o);
                        }
                        selActivo.innerHTML = '<option value="">Seleccione activo</option>';
                        (data.activos || []).forEach(function(v) {
                            var o = document.createElement('option');
                            o.value = v;
                            o.textContent = v;
                            if (v === actActual) o.selected = true;
                            selActivo.appendChild(o);
                        });
                        if (actActual && !(data.activos || []).some(function(v) { return v === actActual; })) {
                            var o = document.createElement('option');
                            o.value = actActual;
                            o.textContent = actActual;
                            o.selected = true;
                            selActivo.appendChild(o);
                        }
                    })
                    .catch(function() {
                        selUbicacion.innerHTML = '<option value="">Seleccione ubicación</option>';
                        selActivo.innerHTML = '<option value="">Seleccione activo</option>';
                    });
            }
            selEmpresa.addEventListener('change', function() {
                llenarOpciones(selEmpresa.value);
            });
        });
    </script>
    <?php
    require_once 'includes/footer.php';
    exit;
}
// Guardar equipo desde HTML
if (isset($_GET['save_html']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['import_html_data'])) {
    $data = $_SESSION['import_html_data'];
    $empresa_id = $_POST['id_empresa'];
    $empresa_stmt = $conn->prepare("SELECT nombre FROM empresas_equipos WHERE id_empresa = ?");
    $empresa_stmt->bind_param("i", $empresa_id);
    $empresa_stmt->execute();
    $empresa_result = $empresa_stmt->get_result();
    $empresa = $empresa_result->fetch_assoc();
    $nombre_cliente = $empresa['nombre'];
    $documentos = null;
    if(isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
        $documentos_data = [];
        $total_files = count($_FILES['documentos']['name']);
        for($i = 0; $i < $total_files; $i++) {
            if($_FILES['documentos']['error'][$i] == 0) {
                $file_name = $_FILES['documentos']['name'][$i];
                $file_type = $_FILES['documentos']['type'][$i];
                $file_content = file_get_contents($_FILES['documentos']['tmp_name'][$i]);
                $documentos_data[] = [
                    'nombre' => $file_name,
                    'tipo' => $file_type,
                    'contenido' => base64_encode($file_content)
                ];
            }
        }
        if(!empty($documentos_data)) {
            $documentos = json_encode($documentos_data);
        }
    }
    
    // Verificar si existe columna id_empresa_usuario en equipos
    $check_column_equipos = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column_equipos = $check_column_equipos && $check_column_equipos->num_rows > 0;
    
    // Verificar permisos: si el usuario tiene empresa asignada, verificar que la empresa de inventario pertenezca a su empresa
    if ($user_empresa_id !== null && isset($empresa_id)) {
        $check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
        $has_empresa_usuario_column_empresas = $check_column_empresas && $check_column_empresas->num_rows > 0;
        
        if ($has_empresa_usuario_column_empresas) {
            $verify_query = "SELECT id_empresa FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $empresa_id, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                die("No tienes permiso para crear equipos para esta empresa");
            }
            $verify_stmt->close();
        }
    }
    
    if ($has_empresa_usuario_column_equipos && $user_empresa_id !== null) {
        // Obtener el siguiente número de equipo para la empresa
        $equipo_number = getNextEquipmentNumber($conn, $user_empresa_id);
        
        // Verificar si existe columna equipo_number
        $check_column_equipo_number = $conn->query("SHOW COLUMNS FROM equipos LIKE 'equipo_number'");
        $has_equipo_number_column = $check_column_equipo_number && $check_column_equipo_number->num_rows > 0;
        
        $ubicacion = $_POST['ubicacion'] ?? '';
        $activo_equipo = $_POST['activo_equipo'] ?? '';
        if ($has_equipo_number_column) {
            // Insertar con id_empresa_usuario y equipo_number
            $stmt = $conn->prepare("INSERT INTO equipos (
                nombre_cliente, nombre_equipo, responsable, sistema_operativo, direccion_mac, tipo, fabricante, modelo, numero_serie, ram, disco_duro, procesador, observaciones, ubicacion, activo_equipo, id_empresa, id_empresa_usuario, equipo_number, documentos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssssssssiss",
                $nombre_cliente,
                $_POST['nombre_equipo'],
                $_POST['responsable'],
                $_POST['sistema_operativo'],
                $_POST['direccion_mac'],
                $_POST['tipo'],
                $_POST['fabricante'],
                $_POST['modelo'],
                $_POST['numero_serie'],
                $_POST['ram'],
                $_POST['disco_duro'],
                $_POST['procesador'],
                $_POST['observaciones'],
                $ubicacion,
                $activo_equipo,
                $empresa_id,
                $user_empresa_id,
                $equipo_number,
                $documentos
            );
        } else {
            // Insertar con id_empresa_usuario sin equipo_number (campo no existe aún)
            $stmt = $conn->prepare("INSERT INTO equipos (
                nombre_cliente, nombre_equipo, responsable, sistema_operativo, direccion_mac, tipo, fabricante, modelo, numero_serie, ram, disco_duro, procesador, observaciones, ubicacion, activo_equipo, id_empresa, id_empresa_usuario, documentos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssssssssis",
                $nombre_cliente,
                $_POST['nombre_equipo'],
                $_POST['responsable'],
                $_POST['sistema_operativo'],
                $_POST['direccion_mac'],
                $_POST['tipo'],
                $_POST['fabricante'],
                $_POST['modelo'],
                $_POST['numero_serie'],
                $_POST['ram'],
                $_POST['disco_duro'],
                $_POST['procesador'],
                $_POST['observaciones'],
                $ubicacion,
                $activo_equipo,
                $empresa_id,
                $user_empresa_id,
                $documentos
            );
        }
    } else {
        // Insertar sin id_empresa_usuario (campo no existe aún)
        $ubicacion = $_POST['ubicacion'] ?? '';
        $activo_equipo = $_POST['activo_equipo'] ?? '';
    $stmt = $conn->prepare("INSERT INTO equipos (
        nombre_cliente, nombre_equipo, responsable, sistema_operativo, direccion_mac, tipo, fabricante, modelo, numero_serie, ram, disco_duro, procesador, observaciones, ubicacion, activo_equipo, id_empresa, documentos
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssssssss",
        $nombre_cliente,
        $_POST['nombre_equipo'],
        $_POST['responsable'],
        $_POST['sistema_operativo'],
        $_POST['direccion_mac'],
        $_POST['tipo'],
        $_POST['fabricante'],
        $_POST['modelo'],
        $_POST['numero_serie'],
        $_POST['ram'],
        $_POST['disco_duro'],
        $_POST['procesador'],
        $_POST['observaciones'],
        $ubicacion,
        $activo_equipo,
        $empresa_id,
        $documentos
    );
    }
    if ($stmt->execute()) {
        require_once '../../log_activity.php';
        logActivity(
            "Importación de equipo (HTML)",
            "equipos",
            $conn->insert_id,
            "Se importó el equipo desde HTML: " . $_POST['nombre_equipo'] . " - De la empresa: " . $nombre_cliente
        );
        unset($_SESSION['import_html_data']);
        header('Location: visualizar-equipos.php');
        exit;
    } else {
        $message = "Error al guardar el equipo: " . $stmt->error;
    }
}
require_once 'includes/header.php';
?>
<style>
    .inv-page-wrap { max-width: 1600px; }
    .inv-brand-bg { background-color: #1E2A3B; }
    .inv-form-card .inv-input { min-height: 42px; }
    .inv-form-card .inv-input:hover { border-color: #cbd5e1; }
    .inv-btn-primary:hover { filter: brightness(1.08); }
    .inicio-header {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
    }
    .inicio-header-subtitle {
        margin: 0.25rem 0 0;
        font-size: 0.9rem;
        color: #6b7280;
    }
    @media (max-width: 768px) {
        .inicio-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .inicio-header h1 { font-size: 1.25rem; }
    }
    /* Tarjeta del formulario (mismo aspecto que agregar/editar equipo) */
    .inv-form-card {
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border: 1px solid #f3f4f6;
    }
    /* Input file: campo y botón "Seleccionar archivo" */
    .inv-form-card input[type="file"] {
        min-height: 42px;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }
    .inv-form-card input[type="file"]:hover {
        border-color: #cbd5e1;
    }
    .inv-form-card input[type="file"]::file-selector-button {
        background-color: #1E2A3B;
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        border: none;
        margin-right: 0.75rem;
        font-weight: 500;
        cursor: pointer;
    }
    .inv-form-card input[type="file"]::file-selector-button:hover {
        filter: brightness(1.08);
    }
    /* Botones secundarios (descarga plantillas) */
    .inv-btn-secondary {
        border-radius: 0.75rem;
        transition: background-color 0.2s, border-color 0.2s;
    }
</style>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg flex-shrink-0">
                <i class="fas fa-file-import text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Importar Equipos</h1>
                <p class="inicio-header-subtitle">Sube un archivo CSV o HTML para importar equipos al inventario.</p>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 text-blue-800 px-4 py-3 text-sm">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3">
            <h3 class="font-semibold mb-2">Errores encontrados:</h3>
            <ul class="list-disc ml-4 text-sm space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="inv-form-card bg-white rounded-2xl shadow-xl border border-gray-100 p-6 lg:p-8">
        <form method="POST" enctype="multipart/form-data" class="mb-4">
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="archivo">
                    <i class="fas fa-file-upload w-4 mr-1 text-gray-400"></i> Seleccionar archivo a importar (CSV o .html)
                </label>
                <input type="file" name="archivo" id="archivo" accept=".csv,.html" required
                       class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-medium file:inv-brand-bg file:text-white file:cursor-pointer">
            </div>

            <div class="flex flex-wrap gap-3 mb-6">
                <a href="Reportes/Plantilla_importar.csv" download="Plantilla_importar.csv"
                   class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                    <i class="fas fa-download"></i>
                    Descargar Plantilla CSV Vacía
                </a>
                <a href="Reportes/Ejemplo_importar.csv" download="Ejemplo_importar.csv"
                   class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                    <i class="fas fa-download"></i>
                    Descargar CSV de Ejemplo con Datos
                </a>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Estructura del archivo CSV</h3>
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">empresa</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">nombre del equipo</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">responsable</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">sistema operativo</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">direccion mac</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">tipo</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">fabricante</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">modelo</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">numero de serie</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ram</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">disco duro</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">procesador</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">observaciones</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ubicación</th>
                                <th class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">activo del equipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 border-b border-gray-100">Empresa ABC</td>
                                <td class="px-4 py-2 border-b border-gray-100">PC-001</td>
                                <td class="px-4 py-2 border-b border-gray-100">Juan Pérez</td>
                                <td class="px-4 py-2 border-b border-gray-100">Windows 10</td>
                                <td class="px-4 py-2 border-b border-gray-100">00:1A:2B:3C:4D:5E</td>
                                <td class="px-4 py-2 border-b border-gray-100">Desktop</td>
                                <td class="px-4 py-2 border-b border-gray-100">Dell</td>
                                <td class="px-4 py-2 border-b border-gray-100">Optiplex 7050</td>
                                <td class="px-4 py-2 border-b border-gray-100">SN12345678</td>
                                <td class="px-4 py-2 border-b border-gray-100">16GB</td>
                                <td class="px-4 py-2 border-b border-gray-100">500GB SSD</td>
                                <td class="px-4 py-2 border-b border-gray-100">Intel i7-7700</td>
                                <td class="px-4 py-2 border-b border-gray-100">Equipo de desarrollo</td>
                                <td class="px-4 py-2 border-b border-gray-100">Oficina central</td>
                                <td class="px-4 py-2 border-b border-gray-100">ACT-001</td>
                            </tr>
                            <tr class="hover:bg-gray-50 bg-gray-50/50">
                                <td class="px-4 py-2 border-b border-gray-100">Empresa ABC</td>
                                <td class="px-4 py-2 border-b border-gray-100">LT-002</td>
                                <td class="px-4 py-2 border-b border-gray-100">María López</td>
                                <td class="px-4 py-2 border-b border-gray-100">Windows 11</td>
                                <td class="px-4 py-2 border-b border-gray-100">AA:BB:CC:DD:EE:FF</td>
                                <td class="px-4 py-2 border-b border-gray-100">Laptop</td>
                                <td class="px-4 py-2 border-b border-gray-100">HP</td>
                                <td class="px-4 py-2 border-b border-gray-100">EliteBook 840</td>
                                <td class="px-4 py-2 border-b border-gray-100">HP87654321</td>
                                <td class="px-4 py-2 border-b border-gray-100">8GB</td>
                                <td class="px-4 py-2 border-b border-gray-100">256GB SSD</td>
                                <td class="px-4 py-2 border-b border-gray-100">Intel i5-1135G7</td>
                                <td class="px-4 py-2 border-b border-gray-100">Equipo de ventas</td>
                                <td class="px-4 py-2 border-b border-gray-100">Sucursal norte</td>
                                <td class="px-4 py-2 border-b border-gray-100">ACT-002</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 border-b border-gray-100">Empresa ABC</td>
                                <td class="px-4 py-2 border-b border-gray-100">SRV-003</td>
                                <td class="px-4 py-2 border-b border-gray-100">Carlos Gómez</td>
                                <td class="px-4 py-2 border-b border-gray-100">Windows Server 2019</td>
                                <td class="px-4 py-2 border-b border-gray-100">11:22:33:44:55:66</td>
                                <td class="px-4 py-2 border-b border-gray-100">Servidor</td>
                                <td class="px-4 py-2 border-b border-gray-100">Lenovo</td>
                                <td class="px-4 py-2 border-b border-gray-100">ThinkSystem SR650</td>
                                <td class="px-4 py-2 border-b border-gray-100">LN98765432</td>
                                <td class="px-4 py-2 border-b border-gray-100">64GB</td>
                                <td class="px-4 py-2 border-b border-gray-100">2TB RAID</td>
                                <td class="px-4 py-2 border-b border-gray-100">Intel Xeon Silver 4210</td>
                                <td class="px-4 py-2 border-b border-gray-100">Servidor de aplicaciones</td>
                                <td class="px-4 py-2 border-b border-gray-100">Sala de servidores</td>
                                <td class="px-4 py-2 border-b border-gray-100">ACT-003</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-gray-600 mt-3">
                    <span class="font-semibold">Nota:</span> Asegúrese de que el nombre de la empresa coincida exactamente con el nombre registrado en el sistema para la importación de CSV.
                </p>
            </div>

            <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                <i class="fas fa-cloud-upload-alt"></i>
                Importar Equipos
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>