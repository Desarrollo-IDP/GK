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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['productos_seleccionados']) || empty($_POST['productos_seleccionados'])) {
    header('Location: generar-carta.php');
    exit;
}

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

// Datos del receptor
$nombre_receptor = limpiarDatos($_POST['nombre_receptor']);
$identificacion_receptor = limpiarDatos($_POST['identificacion_receptor']);
$empresa_receptor = limpiarDatos($_POST['empresa_receptor']);
$area_receptor = limpiarDatos($_POST['area_receptor']);
$fecha_entrega = normalizarFechaAlmacen($_POST['fecha_entrega'] ?? '');
if ($fecha_entrega === null) {
    $fecha_entrega = fechaHoyAlmacen();
}
$tipo_entrega = limpiarDatos($_POST['tipo_entrega']);
$fecha_devolucion = null;
if (!empty($_POST['fecha_devolucion'])) {
    $fecha_devolucion = normalizarFechaAlmacen($_POST['fecha_devolucion']);
    if ($fecha_devolucion === null) {
        $_SESSION['error_carta'] = 'La fecha de devolución no es válida.';
        header('Location: generar-carta.php');
        exit;
    }
}
$observaciones = limpiarDatos($_POST['observaciones']);

// Productos seleccionados
$productos_ids = $_POST['productos_seleccionados'];
$cantidades = $_POST['cantidades'];

// Obtener datos de los productos y VALIDAR CANTIDADES DISPONIBLES
$id_list = implode(',', array_map('intval', $productos_ids));
$query = "SELECT p.*, a.nombre as almacen_nombre, b.nombre as bodega_nombre
          FROM productos_almacen p 
          LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen
          LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega
          WHERE p.id_producto IN ($id_list)";
$result = $conn->query($query);

$productos = [];
$errores_cantidad = [];

while ($row = $result->fetch_assoc()) {
    $id_producto = $row['id_producto'];
    $cantidad_disponible = intval($row['cantidad']);
    $cantidad_solicitada = isset($cantidades[$id_producto]) ? intval($cantidades[$id_producto]) : 0;
    
    // Validar que la cantidad solicitada no exceda la disponible
    if ($cantidad_solicitada > $cantidad_disponible) {
        $errores_cantidad[] = "El producto '{$row['nombre_producto']}' solo tiene {$cantidad_disponible} {$row['unidad']} disponibles, pero se solicitó {$cantidad_solicitada}.";
    }
    
    // Validar que la cantidad sea mayor a 0
    if ($cantidad_solicitada <= 0) {
        $errores_cantidad[] = "La cantidad para el producto '{$row['nombre_producto']}' debe ser mayor a 0.";
    }
    
    $productos[] = $row;
}

// Si hay errores de cantidad, mostrar mensaje y redirigir
if (!empty($errores_cantidad)) {
    $_SESSION['error_carta'] = implode('<br>', $errores_cantidad);
    header('Location: generar-carta.php');
    exit;
}

// Generar número de carta (con contador independiente por empresa)
$numero_carta = generarNumeroCarta($user_empresa_id);

// Iniciar transacción para asegurar atomicidad
$conn->begin_transaction();

try {
    $user = $_SESSION['user']['username'] ?? 'Sistema';
    $stmts = []; // Array para guardar los statements y cerrarlos al final
    
    // Insertar todos los productos de la carta
    foreach ($productos as $producto) {
        $id_producto = $producto['id_producto'];
        $cantidad_entregada = intval($cantidades[$id_producto]);
        
        // Verificar que el producto pertenece a la empresa del usuario (si aplica)
        if ($has_empresa_column && $user_empresa_id !== null) {
            $check_producto = $conn->query("SELECT id_empresa FROM productos_almacen WHERE id_producto = $id_producto");
            if ($check_producto && $row = $check_producto->fetch_assoc()) {
                if ($row['id_empresa'] != $user_empresa_id) {
                    throw new Exception('No tiene permiso para crear cartas con productos de otra empresa');
                }
            }
        }
        
        // Preparar INSERT con o sin id_empresa
        if ($has_empresa_column && $user_empresa_id !== null) {
            $sql = "INSERT INTO cartas_entrega (numero_carta, id_producto, cantidad_entregada, nombre_receptor, identificacion_receptor, empresa_receptor, area_receptor, fecha_entrega, fecha_devolucion, tipo_entrega, estado_entrega, observaciones, firmado_por, id_empresa) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error al preparar consulta: " . $conn->error);
            }
            if (!$stmt->bind_param('siisssssssssi', $numero_carta, $id_producto, $cantidad_entregada, $nombre_receptor, $identificacion_receptor, $empresa_receptor, $area_receptor, $fecha_entrega, $fecha_devolucion, $tipo_entrega, $observaciones, $user, $user_empresa_id)) {
                $stmt->close();
                throw new Exception("Error al bindear parámetros: " . $stmt->error);
            }
        } else {
            $sql = "INSERT INTO cartas_entrega (numero_carta, id_producto, cantidad_entregada, nombre_receptor, identificacion_receptor, empresa_receptor, area_receptor, fecha_entrega, fecha_devolucion, tipo_entrega, estado_entrega, observaciones, firmado_por) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error al preparar consulta: " . $conn->error);
            }
            if (!$stmt->bind_param('siisssssssss', $numero_carta, $id_producto, $cantidad_entregada, $nombre_receptor, $identificacion_receptor, $empresa_receptor, $area_receptor, $fecha_entrega, $fecha_devolucion, $tipo_entrega, $observaciones, $user)) {
                $stmt->close();
                throw new Exception("Error al bindear parámetros: " . $stmt->error);
            }
        }
        
        if (!$stmt->execute()) {
            $error_msg = $stmt->error;
            $error_code = $stmt->errno;
            $stmt->close();
            throw new Exception("Error al ejecutar consulta: $error_msg (errno: $error_code)");
        }
        
        $stmts[] = $stmt; // Guardar para cerrar después
    }
    
    // Cerrar todos los statements
    foreach ($stmts as $stmt) {
        $stmt->close();
    }
    
    // Confirmar transacción
    $conn->commit();
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    error_log("Error en procesar-carta.php: " . $e->getMessage());
    
    // Cerrar cualquier statement que quede abierto
    if (isset($stmts)) {
        foreach ($stmts as $stmt) {
            if ($stmt) {
                $stmt->close();
            }
        }
    }
    
    $_SESSION['error_carta'] = 'Error al crear la carta de entrega: ' . $e->getMessage();
    header('Location: generar-carta.php');
    exit;
}

// Registrar movimiento en historial para cada producto
$user = $_SESSION['user']['username'] ?? 'Sistema';
$check_historial_column = $conn->query("SHOW COLUMNS FROM historial_movimientos LIKE 'id_empresa'");
$has_historial_column = $check_historial_column && $check_historial_column->num_rows > 0;

foreach ($productos as $producto) {
    $id_producto = $producto['id_producto'];
    $cantidad_entregada = intval($cantidades[$id_producto]);
    
    if ($has_historial_column && $user_empresa_id !== null) {
        $historial_stmt = $conn->prepare("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo, fecha_movimiento, id_empresa) VALUES (?, 'Entrega', ?, ?, ?, NOW(), ?)");
        $motivo = "Carta de entrega #$numero_carta";
        $historial_stmt->bind_param('iissi', $id_producto, $cantidad_entregada, $user, $motivo, $user_empresa_id);
        $historial_stmt->execute();
        $historial_stmt->close();
    } else {
        $historial_stmt = $conn->prepare("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo, fecha_movimiento) VALUES (?, 'Entrega', ?, ?, ?, NOW())");
        $motivo = "Carta de entrega #$numero_carta";
        $historial_stmt->bind_param('iiss', $id_producto, $cantidad_entregada, $user, $motivo);
        $historial_stmt->execute();
        $historial_stmt->close();
    }
}

// Actualizar cantidad del producto (opcional - dependiendo de la lógica de negocio)
foreach ($productos as $producto) {
    $id_producto = $producto['id_producto'];
    $cantidad_entregada = intval($cantidades[$id_producto]);
    $conn->query("UPDATE productos_almacen SET cantidad = cantidad - $cantidad_entregada WHERE id_producto = $id_producto");
}

// Guardar número de carta en sesión para generar PDF después de redirigir
// Esto previene que al refrescar se cree otra carta
$_SESSION['carta_generada'] = [
    'numero_carta' => $numero_carta,
    'productos' => $productos,
    'cantidades' => $cantidades,
    'nombre_receptor' => $nombre_receptor,
    'identificacion_receptor' => $identificacion_receptor,
    'empresa_receptor' => $empresa_receptor,
    'area_receptor' => $area_receptor,
    'fecha_entrega' => $fecha_entrega,
    'tipo_entrega' => $tipo_entrega,
    'fecha_devolucion' => $fecha_devolucion,
    'observaciones' => $observaciones
];

// Guardar mensaje de éxito
$_SESSION['success_carta'] = "Carta de entrega {$numero_carta} generada exitosamente. El PDF se abrirá en una nueva ventana.";

// Redirigir a generar-carta.php para recargar la página y actualizar cantidades
// El PDF se abrirá automáticamente desde JavaScript
header('Location: generar-carta.php?generar_pdf=1');
exit;

function generarNumeroCarta($id_empresa_usuario = null) {
    global $conn;
    
    // Si no se proporciona empresa_id, usar 0 para contador global
    if ($id_empresa_usuario === null) {
        $id_empresa_usuario = 0;
    }
    
    // Asegurar que sea un entero
    $id_empresa_usuario = intval($id_empresa_usuario);
    
    // Log para depuración
    error_log("DEBUG generarNumeroCarta: id_empresa_usuario = " . $id_empresa_usuario);
    
    // Verificar si existe la tabla de contadores
    $check_table = $conn->query("SHOW TABLES LIKE 'carta_entrega_counters'");
    $table_exists = $check_table && $check_table->num_rows > 0;
    
    if (!$table_exists) {
        error_log("DEBUG generarNumeroCarta: Tabla no existe, usando método de archivo");
        // Si la tabla no existe, usar método de archivo como fallback
        $empresa_suffix = $id_empresa_usuario !== 0 ? '_empresa_' . $id_empresa_usuario : '';
        $archivo = __DIR__ . '/contador_cartas_almacen' . $empresa_suffix . '.txt';
        
        if (!file_exists($archivo)) {
            file_put_contents($archivo, '0');
        }
        
        $numero = (int)file_get_contents($archivo);
        $numero++;
        file_put_contents($archivo, $numero);
        
        $numero_formateado = 'CA-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
        error_log("DEBUG generarNumeroCarta (archivo): Generado " . $numero_formateado . " para empresa " . $id_empresa_usuario);
        return $numero_formateado;
    }
    
    // Usar tabla de contadores en base de datos (método preferido)
    // Iniciar transacción para asegurar atomicidad
    $conn->begin_transaction();
    
    try {
        // Intentar obtener o crear el contador para esta empresa
        $stmt = $conn->prepare("SELECT current_counter FROM carta_entrega_counters WHERE id_empresa = ? FOR UPDATE");
        if (!$stmt) {
            error_log("ERROR al preparar consulta de contador carta: " . $conn->error);
            $conn->rollback();
            // Fallback a método de archivo
            $empresa_suffix = $id_empresa_usuario !== 0 ? '_empresa_' . $id_empresa_usuario : '';
            $archivo = __DIR__ . '/contador_cartas_almacen' . $empresa_suffix . '.txt';
            if (!file_exists($archivo)) {
                file_put_contents($archivo, '0');
            }
            $numero = (int)file_get_contents($archivo);
            $numero++;
            file_put_contents($archivo, $numero);
            return 'CA-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
        }
        
        $stmt->bind_param("i", $id_empresa_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // El contador existe, incrementarlo
            $row = $result->fetch_assoc();
            $current = intval($row['current_counter']);
            $next_counter = $current + 1;
            
            error_log("DEBUG generarNumeroCarta: Contador existe para empresa " . $id_empresa_usuario . ", actual: " . $current . ", siguiente: " . $next_counter);
            
            // Actualizar el contador
            $update_stmt = $conn->prepare("UPDATE carta_entrega_counters SET current_counter = ? WHERE id_empresa = ?");
            if (!$update_stmt) {
                error_log("ERROR al preparar actualización de contador carta: " . $conn->error);
                $stmt->close();
                $conn->rollback();
                throw new Exception("Error al actualizar contador");
            }
            $update_stmt->bind_param("ii", $next_counter, $id_empresa_usuario);
            if (!$update_stmt->execute()) {
                error_log("ERROR al ejecutar actualización de contador carta: " . $update_stmt->error);
                $update_stmt->close();
                $stmt->close();
                $conn->rollback();
                throw new Exception("Error al ejecutar actualización de contador");
            }
            $update_stmt->close();
        } else {
            // El contador no existe, crearlo con valor inicial 1
            $next_counter = 1;
            error_log("DEBUG generarNumeroCarta: Contador NO existe para empresa " . $id_empresa_usuario . ", creando con valor inicial: " . $next_counter);
            
            $insert_stmt = $conn->prepare("INSERT INTO carta_entrega_counters (id_empresa, current_counter) VALUES (?, ?)");
            if (!$insert_stmt) {
                error_log("ERROR al preparar inserción de contador carta: " . $conn->error);
                $stmt->close();
                $conn->rollback();
                throw new Exception("Error al crear contador");
            }
            $insert_stmt->bind_param("ii", $id_empresa_usuario, $next_counter);
            if (!$insert_stmt->execute()) {
                error_log("ERROR al ejecutar inserción de contador carta: " . $insert_stmt->error);
                $insert_stmt->close();
                $stmt->close();
                $conn->rollback();
                throw new Exception("Error al ejecutar inserción de contador");
            }
            $insert_stmt->close();
        }
        
        $stmt->close();
        
        // Confirmar transacción
        $conn->commit();
        
        // Retornar el número formateado
        $numero_formateado = 'CA-' . str_pad($next_counter, 4, '0', STR_PAD_LEFT);
        error_log("DEBUG generarNumeroCarta: Generado " . $numero_formateado . " para empresa " . $id_empresa_usuario);
        return $numero_formateado;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("ERROR en generarNumeroCarta: " . $e->getMessage() . " para empresa " . $id_empresa_usuario);
        // Fallback a método de archivo
        $empresa_suffix = $id_empresa_usuario !== 0 ? '_empresa_' . $id_empresa_usuario : '';
        $archivo = __DIR__ . '/contador_cartas_almacen' . $empresa_suffix . '.txt';
        if (!file_exists($archivo)) {
            file_put_contents($archivo, '0');
        }
        $numero = (int)file_get_contents($archivo);
        $numero++;
        file_put_contents($archivo, $numero);
        return 'CA-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
}
?>

