<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Gestión de Productos';
require_once '../../includes/header.php';

$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa en productos_almacen y almacenes
$check_column_productos = $conn->query("SHOW COLUMNS FROM productos_almacen LIKE 'id_empresa'");
$has_empresa_column_productos = $check_column_productos && $check_column_productos->num_rows > 0;

$check_column_almacenes = $conn->query("SHOW COLUMNS FROM almacenes LIKE 'id_empresa'");
$has_empresa_column_almacenes = $check_column_almacenes && $check_column_almacenes->num_rows > 0;

$check_column_historial = $conn->query("SHOW COLUMNS FROM historial_movimientos LIKE 'id_empresa'");
$has_empresa_column_historial = $check_column_historial && $check_column_historial->num_rows > 0;

$check_column_catalogos = $conn->query("SHOW COLUMNS FROM catalogos LIKE 'id_empresa'");
$has_empresa_column_catalogos = $check_column_catalogos && $check_column_catalogos->num_rows > 0;

// Procesar acciones
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $id_almacen = intval($_POST['id_almacen']);
        
        // Verificar que el almacén pertenezca a la empresa del usuario
        if ($has_empresa_column_almacenes && $user_empresa_id !== null) {
            $verify_almacen_query = "SELECT id_almacen FROM almacenes WHERE id_almacen = ? AND id_empresa = ?";
            $verify_almacen_stmt = $conn->prepare($verify_almacen_query);
            $verify_almacen_stmt->bind_param("ii", $id_almacen, $user_empresa_id);
            $verify_almacen_stmt->execute();
            $verify_almacen_result = $verify_almacen_stmt->get_result();
            
            if ($verify_almacen_result->num_rows === 0) {
                $error_message = 'No tienes permiso para crear productos en este almacén';
                $verify_almacen_stmt->close();
            } else {
                $verify_almacen_stmt->close();
                
                $nombre_producto = limpiarDatos($_POST['nombre_producto']);
                $codigo_producto = limpiarDatos($_POST['codigo_producto']);
                $descripcion = !empty($_POST['descripcion']) ? limpiarDatos($_POST['descripcion']) : '';
                $categoria = !empty($_POST['categoria']) ? limpiarDatos($_POST['categoria']) : '';
                $marca = !empty($_POST['marca']) ? limpiarDatos($_POST['marca']) : '';
                $modelo = !empty($_POST['modelo']) ? limpiarDatos($_POST['modelo']) : '';
                $numero_serie = !empty($_POST['numero_serie']) ? limpiarDatos($_POST['numero_serie']) : '';
                $cantidad = intval($_POST['cantidad']);
                $unidad = limpiarDatos($_POST['unidad']);
                $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : 0;
                $ubicacion_exacta = !empty($_POST['ubicacion_exacta']) ? limpiarDatos($_POST['ubicacion_exacta']) : '';
                $lote = !empty($_POST['lote']) ? limpiarDatos($_POST['lote']) : '';
                $fecha_ingreso = limpiarDatos($_POST['fecha_ingreso']);
                $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? limpiarDatos($_POST['fecha_vencimiento']) : NULL;
                $estado_producto = !empty($_POST['estado_producto']) ? limpiarDatos($_POST['estado_producto']) : 'Disponible';
                $stock_minimo = !empty($_POST['stock_minimo']) ? intval($_POST['stock_minimo']) : 0;
                $stock_maximo = !empty($_POST['stock_maximo']) ? intval($_POST['stock_maximo']) : 0;
                $proveedor = !empty($_POST['proveedor']) ? limpiarDatos($_POST['proveedor']) : '';
                $observaciones = !empty($_POST['observaciones']) ? limpiarDatos($_POST['observaciones']) : '';
                
                $valor_total = $cantidad * $precio_unitario;
                
                // Construir query según si existe columna id_empresa
                if ($has_empresa_column_productos && $user_empresa_id !== null) {
                    $sql = "INSERT INTO productos_almacen (id_almacen, nombre_producto, codigo_producto, descripcion, categoria, marca, modelo, numero_serie, cantidad, unidad, precio_unitario, valor_total, ubicacion_exacta, lote, fecha_ingreso, fecha_vencimiento, estado_producto, stock_minimo, stock_maximo, proveedor, observaciones, id_empresa) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('isssssssisddsssssiissi', $id_almacen, $nombre_producto, $codigo_producto, $descripcion, $categoria, $marca, $modelo, $numero_serie, $cantidad, $unidad, $precio_unitario, $valor_total, $ubicacion_exacta, $lote, $fecha_ingreso, $fecha_vencimiento, $estado_producto, $stock_minimo, $stock_maximo, $proveedor, $observaciones, $user_empresa_id);
                } else {
                    $sql = "INSERT INTO productos_almacen (id_almacen, nombre_producto, codigo_producto, descripcion, categoria, marca, modelo, numero_serie, cantidad, unidad, precio_unitario, valor_total, ubicacion_exacta, lote, fecha_ingreso, fecha_vencimiento, estado_producto, stock_minimo, stock_maximo, proveedor, observaciones) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('isssssssisddsssssiiss', $id_almacen, $nombre_producto, $codigo_producto, $descripcion, $categoria, $marca, $modelo, $numero_serie, $cantidad, $unidad, $precio_unitario, $valor_total, $ubicacion_exacta, $lote, $fecha_ingreso, $fecha_vencimiento, $estado_producto, $stock_minimo, $stock_maximo, $proveedor, $observaciones);
                }
                
                try {
                    if ($stmt->execute()) {
                        $success_message = 'Producto creado exitosamente';
                        
                        // Registrar en historial
                        $id_producto = $conn->insert_id;
                        $user = $_SESSION['user']['username'] ?? 'Sistema';
                        
                        // Construir query de historial según si existe columna id_empresa
                        if ($has_empresa_column_historial && $user_empresa_id !== null) {
                            $historial_sql = "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo, id_empresa) VALUES (?, 'Entrada', ?, ?, 'Registro inicial de producto', ?)";
                            $historial_stmt = $conn->prepare($historial_sql);
                            $historial_stmt->bind_param('iisi', $id_producto, $cantidad, $user, $user_empresa_id);
                            $historial_stmt->execute();
                            $historial_stmt->close();
                        } else {
                            $conn->query("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo) VALUES ($id_producto, 'Entrada', $cantidad, '$user', 'Registro inicial de producto')");
                        }
                    } else {
                        // Verificar si es error de código duplicado
                        if ($stmt->errno == 1062) {
                            $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                        } else {
                            $error_message = 'Error al crear el producto: ' . $stmt->error;
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    // Capturar excepciones de MySQL
                    if ($e->getCode() == 1062) {
                        $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                    } else {
                        $error_message = 'Error al crear el producto. Por favor, intente nuevamente.';
                    }
                }
                $stmt->close();
            }
        } else {
            $nombre_producto = limpiarDatos($_POST['nombre_producto']);
            $codigo_producto = limpiarDatos($_POST['codigo_producto']);
            $descripcion = !empty($_POST['descripcion']) ? limpiarDatos($_POST['descripcion']) : '';
            $categoria = !empty($_POST['categoria']) ? limpiarDatos($_POST['categoria']) : '';
            $marca = !empty($_POST['marca']) ? limpiarDatos($_POST['marca']) : '';
            $modelo = !empty($_POST['modelo']) ? limpiarDatos($_POST['modelo']) : '';
            $numero_serie = !empty($_POST['numero_serie']) ? limpiarDatos($_POST['numero_serie']) : '';
            $cantidad = intval($_POST['cantidad']);
            $unidad = limpiarDatos($_POST['unidad']);
            $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : 0;
            $ubicacion_exacta = !empty($_POST['ubicacion_exacta']) ? limpiarDatos($_POST['ubicacion_exacta']) : '';
            $lote = !empty($_POST['lote']) ? limpiarDatos($_POST['lote']) : '';
            $fecha_ingreso = limpiarDatos($_POST['fecha_ingreso']);
            $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? limpiarDatos($_POST['fecha_vencimiento']) : NULL;
            $estado_producto = !empty($_POST['estado_producto']) ? limpiarDatos($_POST['estado_producto']) : 'Disponible';
            $stock_minimo = !empty($_POST['stock_minimo']) ? intval($_POST['stock_minimo']) : 0;
            $stock_maximo = !empty($_POST['stock_maximo']) ? intval($_POST['stock_maximo']) : 0;
            $proveedor = !empty($_POST['proveedor']) ? limpiarDatos($_POST['proveedor']) : '';
            $observaciones = !empty($_POST['observaciones']) ? limpiarDatos($_POST['observaciones']) : '';
            
            $valor_total = $cantidad * $precio_unitario;
            
            $sql = "INSERT INTO productos_almacen (id_almacen, nombre_producto, codigo_producto, descripcion, categoria, marca, modelo, numero_serie, cantidad, unidad, precio_unitario, valor_total, ubicacion_exacta, lote, fecha_ingreso, fecha_vencimiento, estado_producto, stock_minimo, stock_maximo, proveedor, observaciones) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isssssssisddsssssiiss', $id_almacen, $nombre_producto, $codigo_producto, $descripcion, $categoria, $marca, $modelo, $numero_serie, $cantidad, $unidad, $precio_unitario, $valor_total, $ubicacion_exacta, $lote, $fecha_ingreso, $fecha_vencimiento, $estado_producto, $stock_minimo, $stock_maximo, $proveedor, $observaciones);
            
            try {
                if ($stmt->execute()) {
                    $success_message = 'Producto creado exitosamente';
                    
                    // Registrar en historial
                    $id_producto = $conn->insert_id;
                    $user = $_SESSION['user']['username'] ?? 'Sistema';
                    $conn->query("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo) VALUES ($id_producto, 'Entrada', $cantidad, '$user', 'Registro inicial de producto')");
                } else {
                    // Verificar si es error de código duplicado
                    if ($stmt->errno == 1062) {
                        $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                    } else {
                        $error_message = 'Error al crear el producto: ' . $stmt->error;
                    }
                }
            } catch (mysqli_sql_exception $e) {
                // Capturar excepciones de MySQL
                if ($e->getCode() == 1062) {
                    $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                } else {
                    $error_message = 'Error al crear el producto. Por favor, intente nuevamente.';
                }
            }
            $stmt->close();
        }
    }
    
    if ($action === 'update') {
        $id_producto = intval($_POST['id_producto']);
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que el producto pertenezca a su empresa
        if ($has_empresa_column_productos && $user_empresa_id !== null) {
            $verify_query = "SELECT id_producto FROM productos_almacen WHERE id_producto = ? AND id_empresa = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $id_producto, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tienes permiso para editar este producto'; 
                $verify_stmt->close();
            } else {
                $verify_stmt->close();
                
                $id_almacen = intval($_POST['id_almacen']);
                
                // Verificar que el almacén también pertenezca a la empresa del usuario
                if ($has_empresa_column_almacenes) {
                    $verify_almacen_query = "SELECT id_almacen FROM almacenes WHERE id_almacen = ? AND id_empresa = ?";
                    $verify_almacen_stmt = $conn->prepare($verify_almacen_query);
                    $verify_almacen_stmt->bind_param("ii", $id_almacen, $user_empresa_id);
                    $verify_almacen_stmt->execute();
                    $verify_almacen_result = $verify_almacen_stmt->get_result();
                    
                    if ($verify_almacen_result->num_rows === 0) {
                        $error_message = 'No tienes permiso para asignar este producto a ese almacén';
                        $verify_almacen_stmt->close();
                    } else {
                        $verify_almacen_stmt->close();
                        
                        // Obtener cantidad actual del producto (no se puede editar en este formulario)
                        $cantidad_actual_query = "SELECT cantidad FROM productos_almacen WHERE id_producto = ? AND id_empresa = ?";
                        $cantidad_actual_stmt = $conn->prepare($cantidad_actual_query);
                        $cantidad_actual_stmt->bind_param("ii", $id_producto, $user_empresa_id);
                        $cantidad_actual_stmt->execute();
                        $cantidad_actual_result = $cantidad_actual_stmt->get_result();
                        $cantidad_actual_data = $cantidad_actual_result->fetch_assoc();
                        $cantidad = intval($cantidad_actual_data['cantidad'] ?? 0);
                        $cantidad_actual_stmt->close();
                        
                        $nombre_producto = limpiarDatos($_POST['nombre_producto']);
                        $codigo_producto = limpiarDatos($_POST['codigo_producto']);
                        $descripcion = !empty($_POST['descripcion']) ? limpiarDatos($_POST['descripcion']) : '';
                        $categoria = !empty($_POST['categoria']) ? limpiarDatos($_POST['categoria']) : '';
                        $marca = !empty($_POST['marca']) ? limpiarDatos($_POST['marca']) : '';
                        $modelo = !empty($_POST['modelo']) ? limpiarDatos($_POST['modelo']) : '';
                        $numero_serie = !empty($_POST['numero_serie']) ? limpiarDatos($_POST['numero_serie']) : '';
                        $unidad = limpiarDatos($_POST['unidad']);
                        $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : 0;
                        $ubicacion_exacta = !empty($_POST['ubicacion_exacta']) ? limpiarDatos($_POST['ubicacion_exacta']) : '';
                        $lote = !empty($_POST['lote']) ? limpiarDatos($_POST['lote']) : '';
                        $fecha_ingreso = limpiarDatos($_POST['fecha_ingreso']);
                        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? limpiarDatos($_POST['fecha_vencimiento']) : NULL;
                        $estado_producto = !empty($_POST['estado_producto']) ? limpiarDatos($_POST['estado_producto']) : 'Disponible';
                        $stock_minimo = !empty($_POST['stock_minimo']) ? intval($_POST['stock_minimo']) : 0;
                        $stock_maximo = !empty($_POST['stock_maximo']) ? intval($_POST['stock_maximo']) : 0;
                        $proveedor = !empty($_POST['proveedor']) ? limpiarDatos($_POST['proveedor']) : '';
                        $observaciones = !empty($_POST['observaciones']) ? limpiarDatos($_POST['observaciones']) : '';
                        
                        $valor_total = $cantidad * $precio_unitario;
                        
                        $sql = "UPDATE productos_almacen SET id_almacen=?, nombre_producto=?, codigo_producto=?, descripcion=?, categoria=?, marca=?, modelo=?, numero_serie=?, cantidad=?, unidad=?, precio_unitario=?, valor_total=?, ubicacion_exacta=?, lote=?, fecha_ingreso=?, fecha_vencimiento=?, estado_producto=?, stock_minimo=?, stock_maximo=?, proveedor=?, observaciones=? WHERE id_producto=? AND id_empresa=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('isssssssisddsssssiissii', $id_almacen, $nombre_producto, $codigo_producto, $descripcion, $categoria, $marca, $modelo, $numero_serie, $cantidad, $unidad, $precio_unitario, $valor_total, $ubicacion_exacta, $lote, $fecha_ingreso, $fecha_vencimiento, $estado_producto, $stock_minimo, $stock_maximo, $proveedor, $observaciones, $id_producto, $user_empresa_id);
                        
                        try {
                            if ($stmt->execute() && $stmt->affected_rows === 1) {
                                $success_message = 'Producto actualizado exitosamente';
                            } else {
                                // Verificar si es error de código duplicado
                                if ($stmt->errno == 1062) {
                                    $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                                } else {
                                    $error_message = 'Error al actualizar el producto: ' . $stmt->error;
                                }
                            }
                        } catch (mysqli_sql_exception $e) {
                            // Capturar excepciones de MySQL
                            if ($e->getCode() == 1062) {
                                $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                            } else {
                                $error_message = 'Error al actualizar el producto. Por favor, intente nuevamente.';
                            }
                        }
                    }
                } else {
                    // Obtener cantidad actual del producto (no se puede editar en este formulario)
                    $cantidad_actual_query = "SELECT cantidad FROM productos_almacen WHERE id_producto = ? AND id_empresa = ?";
                    $cantidad_actual_stmt = $conn->prepare($cantidad_actual_query);
                    $cantidad_actual_stmt->bind_param("ii", $id_producto, $user_empresa_id);
                    $cantidad_actual_stmt->execute();
                    $cantidad_actual_result = $cantidad_actual_stmt->get_result();
                    $cantidad_actual_data = $cantidad_actual_result->fetch_assoc();
                    $cantidad = intval($cantidad_actual_data['cantidad'] ?? 0);
                    $cantidad_actual_stmt->close();
                    
                    $nombre_producto = limpiarDatos($_POST['nombre_producto']);
                    $codigo_producto = limpiarDatos($_POST['codigo_producto']);
                    $descripcion = !empty($_POST['descripcion']) ? limpiarDatos($_POST['descripcion']) : '';
                    $categoria = !empty($_POST['categoria']) ? limpiarDatos($_POST['categoria']) : '';
                    $marca = !empty($_POST['marca']) ? limpiarDatos($_POST['marca']) : '';
                    $modelo = !empty($_POST['modelo']) ? limpiarDatos($_POST['modelo']) : '';
                    $numero_serie = !empty($_POST['numero_serie']) ? limpiarDatos($_POST['numero_serie']) : '';
                    $unidad = limpiarDatos($_POST['unidad']);
                    $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : 0;
                    $ubicacion_exacta = !empty($_POST['ubicacion_exacta']) ? limpiarDatos($_POST['ubicacion_exacta']) : '';
                    $lote = !empty($_POST['lote']) ? limpiarDatos($_POST['lote']) : '';
                    $fecha_ingreso = limpiarDatos($_POST['fecha_ingreso']);
                    $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? limpiarDatos($_POST['fecha_vencimiento']) : NULL;
                    $estado_producto = !empty($_POST['estado_producto']) ? limpiarDatos($_POST['estado_producto']) : 'Disponible';
                    $stock_minimo = !empty($_POST['stock_minimo']) ? intval($_POST['stock_minimo']) : 0;
                    $stock_maximo = !empty($_POST['stock_maximo']) ? intval($_POST['stock_maximo']) : 0;
                    $proveedor = !empty($_POST['proveedor']) ? limpiarDatos($_POST['proveedor']) : '';
                    $observaciones = !empty($_POST['observaciones']) ? limpiarDatos($_POST['observaciones']) : '';
                    
                    $valor_total = $cantidad * $precio_unitario;
                    
                    $sql = "UPDATE productos_almacen SET id_almacen=?, nombre_producto=?, codigo_producto=?, descripcion=?, categoria=?, marca=?, modelo=?, numero_serie=?, cantidad=?, unidad=?, precio_unitario=?, valor_total=?, ubicacion_exacta=?, lote=?, fecha_ingreso=?, fecha_vencimiento=?, estado_producto=?, stock_minimo=?, stock_maximo=?, proveedor=?, observaciones=? WHERE id_producto=? AND id_empresa=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('isssssssisddsssssiissii', $id_almacen, $nombre_producto, $codigo_producto, $descripcion, $categoria, $marca, $modelo, $numero_serie, $cantidad, $unidad, $precio_unitario, $valor_total, $ubicacion_exacta, $lote, $fecha_ingreso, $fecha_vencimiento, $estado_producto, $stock_minimo, $stock_maximo, $proveedor, $observaciones, $id_producto, $user_empresa_id);
                    
                    try {
                        if ($stmt->execute() && $stmt->affected_rows === 1) {
                            $success_message = 'Producto actualizado exitosamente';
                        } else {
                            // Verificar si es error de código duplicado
                            if ($stmt->errno == 1062) {
                                $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                            } else {
                                $error_message = 'Error al actualizar el producto: ' . $stmt->error;
                            }
                        }
                    } catch (mysqli_sql_exception $e) {
                        // Capturar excepciones de MySQL
                        if ($e->getCode() == 1062) {
                            $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                        } else {
                            $error_message = 'Error al actualizar el producto. Por favor, intente nuevamente.';
                        }
                    }
                }
            }
        } else {
            // Obtener cantidad actual del producto (no se puede editar en este formulario)
            $cantidad_actual_query = "SELECT cantidad FROM productos_almacen WHERE id_producto = ?";
            $cantidad_actual_stmt = $conn->prepare($cantidad_actual_query);
            $cantidad_actual_stmt->bind_param("i", $id_producto);
            $cantidad_actual_stmt->execute();
            $cantidad_actual_result = $cantidad_actual_stmt->get_result();
            $cantidad_actual_data = $cantidad_actual_result->fetch_assoc();
            $cantidad = intval($cantidad_actual_data['cantidad'] ?? 0);
            $cantidad_actual_stmt->close();
            
            $id_almacen = intval($_POST['id_almacen']);
            $nombre_producto = limpiarDatos($_POST['nombre_producto']);
            $codigo_producto = limpiarDatos($_POST['codigo_producto']);
            $descripcion = !empty($_POST['descripcion']) ? limpiarDatos($_POST['descripcion']) : '';
            $categoria = !empty($_POST['categoria']) ? limpiarDatos($_POST['categoria']) : '';
            $marca = !empty($_POST['marca']) ? limpiarDatos($_POST['marca']) : '';
            $modelo = !empty($_POST['modelo']) ? limpiarDatos($_POST['modelo']) : '';
            $numero_serie = !empty($_POST['numero_serie']) ? limpiarDatos($_POST['numero_serie']) : '';
            $unidad = limpiarDatos($_POST['unidad']);
            $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : 0;
            $ubicacion_exacta = !empty($_POST['ubicacion_exacta']) ? limpiarDatos($_POST['ubicacion_exacta']) : '';
            $lote = !empty($_POST['lote']) ? limpiarDatos($_POST['lote']) : '';
            $fecha_ingreso = limpiarDatos($_POST['fecha_ingreso']);
            $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? limpiarDatos($_POST['fecha_vencimiento']) : NULL;
            $estado_producto = !empty($_POST['estado_producto']) ? limpiarDatos($_POST['estado_producto']) : 'Disponible';
            $stock_minimo = !empty($_POST['stock_minimo']) ? intval($_POST['stock_minimo']) : 0;
            $stock_maximo = !empty($_POST['stock_maximo']) ? intval($_POST['stock_maximo']) : 0;
            $proveedor = !empty($_POST['proveedor']) ? limpiarDatos($_POST['proveedor']) : '';
            $observaciones = !empty($_POST['observaciones']) ? limpiarDatos($_POST['observaciones']) : '';
            
            $valor_total = $cantidad * $precio_unitario;
            
            $sql = "UPDATE productos_almacen SET id_almacen=?, nombre_producto=?, codigo_producto=?, descripcion=?, categoria=?, marca=?, modelo=?, numero_serie=?, cantidad=?, unidad=?, precio_unitario=?, valor_total=?, ubicacion_exacta=?, lote=?, fecha_ingreso=?, fecha_vencimiento=?, estado_producto=?, stock_minimo=?, stock_maximo=?, proveedor=?, observaciones=? WHERE id_producto=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isssssssisddsssssiissi', $id_almacen, $nombre_producto, $codigo_producto, $descripcion, $categoria, $marca, $modelo, $numero_serie, $cantidad, $unidad, $precio_unitario, $valor_total, $ubicacion_exacta, $lote, $fecha_ingreso, $fecha_vencimiento, $estado_producto, $stock_minimo, $stock_maximo, $proveedor, $observaciones, $id_producto);
            
            try {
                if ($stmt->execute()) {
                    $success_message = 'Producto actualizado exitosamente';
                } else {
                    // Verificar si es error de código duplicado
                    if ($stmt->errno == 1062) {
                        $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                    } else {
                        $error_message = 'Error al actualizar el producto: ' . $stmt->error;
                    }
                }
            } catch (mysqli_sql_exception $e) {
                // Capturar excepciones de MySQL
                if ($e->getCode() == 1062) {
                    $error_message = 'El código de producto "' . htmlspecialchars($codigo_producto) . '" ya existe. Por favor, use un código diferente.';
                } else {
                    $error_message = 'Error al actualizar el producto. Por favor, intente nuevamente.';
                }
            }
        }
    }
    
    if ($action === 'delete') {
        $id_producto = intval($_POST['id_producto']);
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que el producto pertenezca a su empresa
        if ($has_empresa_column_productos && $user_empresa_id !== null) {
            $verify_query = "SELECT id_producto FROM productos_almacen WHERE id_producto = ? AND id_empresa = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $id_producto, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tienes permiso para eliminar este producto';
                $verify_stmt->close();
            } else {
                $verify_stmt->close();
                
                $sql = "DELETE FROM productos_almacen WHERE id_producto = ? AND id_empresa = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ii', $id_producto, $user_empresa_id);
                
                if ($stmt->execute() && $stmt->affected_rows === 1) {
                    $success_message = 'Producto eliminado exitosamente';
                } else {
                    $error_message = 'Error al eliminar el producto: ' . $conn->error;
                }
            }
        } else {
            $sql = "DELETE FROM productos_almacen WHERE id_producto = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id_producto);
            
            if ($stmt->execute()) {
                $success_message = 'Producto eliminado exitosamente';
            } else {
                $error_message = 'Error al eliminar el producto: ' . $conn->error;
            }
        }
    }
    
    if ($action === 'ajustar_cantidad') {
        $id_producto = intval($_POST['id_producto']);
        $nueva_cantidad = intval($_POST['nueva_cantidad']);
        // Obtener motivo del POST y asegurar que sea string
        $motivo = '';
        if (isset($_POST['motivo']) && is_string($_POST['motivo'])) {
            $motivo = trim($_POST['motivo']);
        }
        
        // Si está vacío, usar valor por defecto
        if (empty($motivo)) {
            $motivo = 'Ajuste de inventario';
        }
        
        // Limpiar datos pero asegurar que siga siendo string
        if (function_exists('limpiarDatos')) {
            $motivo = limpiarDatos($motivo);
        }
        
        // Asegurar que sea string (no número)
        $motivo = (string)$motivo;
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que el producto pertenezca a su empresa
        if ($has_empresa_column_productos && $user_empresa_id !== null) {
            $verify_query = "SELECT id_producto, cantidad FROM productos_almacen WHERE id_producto = ? AND id_empresa = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $id_producto, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tienes permiso para ajustar este producto';
                $verify_stmt->close();
            } else {
                $producto_data = $verify_result->fetch_assoc();
                $cantidad_anterior = intval($producto_data['cantidad']);
                $verify_stmt->close();
                
                // Calcular diferencia para el historial
                $diferencia = $nueva_cantidad - $cantidad_anterior;
                $tipo_movimiento = 'Ajuste'; // Cambiar a "Ajuste" en lugar de "Entrada" o "Salida"
                
                // Actualizar cantidad y recalcular valor_total
                $producto_info = $conn->query("SELECT precio_unitario FROM productos_almacen WHERE id_producto = $id_producto")->fetch_assoc();
                $precio_unitario = floatval($producto_info['precio_unitario'] ?? 0);
                $nuevo_valor_total = $nueva_cantidad * $precio_unitario;
                
                $sql = "UPDATE productos_almacen SET cantidad = ?, valor_total = ? WHERE id_producto = ? AND id_empresa = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('idii', $nueva_cantidad, $nuevo_valor_total, $id_producto, $user_empresa_id);
                
                if ($stmt->execute() && $stmt->affected_rows === 1) {
                    $success_message = 'Cantidad ajustada exitosamente';
                    
                    // Registrar en historial con motivo formateado
                    $user = $_SESSION['user']['username'] ?? 'Sistema';
                    $cantidad_movimiento = abs($diferencia);
                    $motivo_ajuste = 'Ajuste: ' . $motivo; // Formatear motivo como "Ajuste: [texto]"
                    
                    // Debug: Verificar que el motivo no esté vacío
                    if (empty($motivo) || $motivo === '0') {
                        $motivo_ajuste = 'Ajuste: Sin motivo especificado';
                    }
                    
                    if ($has_empresa_column_historial) {
                        $historial_sql = "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo, id_empresa) VALUES (?, ?, ?, ?, ?, ?)";
                        $historial_stmt = $conn->prepare($historial_sql);
                        // Verificar que motivo_ajuste sea string antes de bind
                        if (!is_string($motivo_ajuste)) {
                            $motivo_ajuste = (string)$motivo_ajuste;
                        }
                        $historial_stmt->bind_param('isissi', $id_producto, $tipo_movimiento, $cantidad_movimiento, $user, $motivo_ajuste, $user_empresa_id);
                        $historial_stmt->execute();
                        $historial_stmt->close();
                    } else {
                        $conn->query("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo) VALUES ($id_producto, '$tipo_movimiento', $cantidad_movimiento, '$user', '$motivo_ajuste')");
                    }
                } else {
                    $error_message = 'Error al ajustar la cantidad: ' . $conn->error;
                }
            }
        } else {
            // Obtener cantidad anterior
            $producto_data = $conn->query("SELECT cantidad, precio_unitario FROM productos_almacen WHERE id_producto = $id_producto")->fetch_assoc();
            $cantidad_anterior = intval($producto_data['cantidad']);
            $precio_unitario = floatval($producto_data['precio_unitario'] ?? 0);
            
            // Calcular diferencia para el historial
            $diferencia = $nueva_cantidad - $cantidad_anterior;
            $tipo_movimiento = 'Ajuste'; // Cambiar a "Ajuste" en lugar de "Entrada" o "Salida"
            $nuevo_valor_total = $nueva_cantidad * $precio_unitario;
            
            $sql = "UPDATE productos_almacen SET cantidad = ?, valor_total = ? WHERE id_producto = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('idi', $nueva_cantidad, $nuevo_valor_total, $id_producto);
            
            if ($stmt->execute()) {
                $success_message = 'Cantidad ajustada exitosamente';
                
                // Registrar en historial con motivo formateado
                $user = $_SESSION['user']['username'] ?? 'Sistema';
                $cantidad_movimiento = abs($diferencia);
                $motivo_ajuste = 'Ajuste: ' . $motivo; // Formatear motivo como "Ajuste: [texto]"
                
                // Debug: Verificar que el motivo no esté vacío
                if (empty($motivo) || $motivo === '0') {
                    $motivo_ajuste = 'Ajuste: Sin motivo especificado';
                }
                
                // Escapar el motivo para la consulta SQL
                $motivo_ajuste_escaped = $conn->real_escape_string($motivo_ajuste);
                $conn->query("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo) VALUES ($id_producto, '$tipo_movimiento', $cantidad_movimiento, '$user', '$motivo_ajuste_escaped')");
            } else {
                $error_message = 'Error al ajustar la cantidad: ' . $conn->error;
            }
        }
    }
}

// Filtros
$filter_stock_bajo = isset($_GET['filter']) && $_GET['filter'] === 'stock_bajo';
$filter_almacen = $_GET['almacen'] ?? '';
$filter_categoria = $_GET['categoria'] ?? '';

// Obtener lista de productos con filtro por empresa
$productos_query = "SELECT p.*, a.nombre as almacen_nombre, a.codigo as almacen_codigo, b.nombre as bodega_nombre
                    FROM productos_almacen p 
                    LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen
                    LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega";

$productos_params = [];
$productos_types = '';
$where_added = false;

// Agregar filtro por empresa del usuario si la columna existe
if ($has_empresa_column_productos) {
    if ($user_empresa_id !== null) {
        // Usuario con empresa asignada: solo ver sus productos
        $productos_query .= " WHERE p.id_empresa = ?";
        $productos_params[] = $user_empresa_id;
        $productos_types = 'i';
        $where_added = true;
    }
    // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
}

if (!$where_added) {
    $productos_query .= " WHERE 1=1";
}

if ($filter_stock_bajo) {
    $productos_query .= " AND p.cantidad <= p.stock_minimo AND p.stock_minimo > 0";
}
if ($filter_almacen) {
    $productos_query .= " AND p.id_almacen = " . intval($filter_almacen);
}
if ($filter_categoria) {
    $productos_query .= " AND p.categoria = '" . $conn->real_escape_string($filter_categoria) . "'";
}

$productos_query .= " ORDER BY p.fecha_creacion DESC";

if (!empty($productos_params)) {
    $productos_stmt = $conn->prepare($productos_query);
    if ($productos_stmt) {
        $productos_stmt->bind_param($productos_types, ...$productos_params);
        $productos_stmt->execute();
        $productos = $productos_stmt->get_result();
    } else {
        $error_message = 'Error en consulta: ' . $conn->error;
        $productos = $conn->query("SELECT p.*, a.nombre as almacen_nombre, a.codigo as almacen_codigo, b.nombre as bodega_nombre FROM productos_almacen p LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega ORDER BY p.fecha_creacion DESC");
    }
} else {
    $productos = $conn->query($productos_query);
}

// Obtener almacenes para el selector con filtro por empresa
$almacenes_query = "SELECT a.*, b.nombre as bodega_nombre FROM almacenes a LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega WHERE a.estado_almacen IN ('Disponible', 'Ocupado')";
$almacenes_params = [];
$almacenes_types = '';

// Agregar filtro por empresa si la columna existe
if ($has_empresa_column_almacenes) {
    if ($user_empresa_id !== null) {
        $almacenes_query .= " AND a.id_empresa = ?";
        $almacenes_params[] = $user_empresa_id;
        $almacenes_types = 'i';
    }
    // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
}

$almacenes_query .= " ORDER BY a.nombre";

if (!empty($almacenes_params)) {
    $almacenes_stmt = $conn->prepare($almacenes_query);
    $almacenes_stmt->bind_param($almacenes_types, ...$almacenes_params);
    $almacenes_stmt->execute();
    $almacenes = $almacenes_stmt->get_result();
} else {
    $almacenes = $conn->query($almacenes_query);
}
?>

<style>
    /* Estilos mejorados para productos.php */
    .productos-header {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #1E2A3B;
    }
    
    .productos-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .productos-header h1 i {
        color: #1E2A3B;
        font-size: 1.5rem;
    }
    
    .productos-header .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(30, 42, 59, 0.2);
    }
    
    .productos-header .btn-primary:hover {
        background-color: #000000;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(30, 42, 59, 0.3);
    }
    
    .filters-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border: 1px solid #E5E7EB;
    }
    
    .filters-card .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1.25rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
    }
    
    .filters-card .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    .table-container {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        border: 1px solid #E5E7EB;
    }
    
    .table-container table thead {
        background-color: #1E2A3B;
    }
    
    .table-container table thead th {
        padding: 1rem 1.25rem;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #FFFFFF;
        border-bottom: 2px solid #000000;
    }
    
    .table-container table tbody tr {
        border-bottom: 1px solid #E5E7EB;
        transition: background-color 0.2s ease;
    }
    
    .table-container table tbody tr:hover {
        background-color: #F9FAFB;
    }
    
    .table-container table tbody tr:last-child {
        border-bottom: none;
    }
    
    .table-container table tbody td {
        padding: 1rem 1.25rem;
        font-size: 0.9375rem;
        color: #1E2A3B;
    }
    
    .table-container table tbody td strong {
        color: #1E2A3B;
        font-weight: 600;
    }
    
    .table-container table tbody td code {
        background: #F3F4F6;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        color: #1E2A3B;
        font-weight: 600;
    }
    
    .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .btn-sm {
        padding: 0.5rem 0.875rem;
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-right: 0.5rem;
    }
    
    .btn-sm:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .btn-sm:last-child {
        margin-right: 0;
    }
    
    .btn-warning {
        background-color: #f59e0b;
        color: white;
        border-color: #f59e0b;
    }
    
    .btn-warning:hover {
        background-color: #d97706;
        border-color: #d97706;
        color: white;
    }
    
    .form-control:disabled {
        background-color: #f3f4f6;
        cursor: not-allowed;
        opacity: 0.7;
    }
    
    .modal-overlay {
        z-index: 9999;
        backdrop-filter: blur(2px);
    }
    
    .modal {
        max-width: 900px;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        z-index: 10000;
        animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .modal-header {
        background-color: #1E2A3B;
        padding: 1.5rem 2rem;
        border-bottom: 2px solid #000000;
    }
    
    .modal-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .modal-header h3 i {
        font-size: 1.25rem;
    }
    
    .modal-close {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .modal-body {
        padding: 2rem;
        max-height: calc(90vh - 200px);
        overflow-y: auto;
    }
    
    .modal-footer {
        padding: 1.25rem 2rem;
        background-color: #F9FAFB;
        border-top: 1px solid #E5E7EB;
    }
    
    .modal-footer .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .modal-footer .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        color: #1E2A3B;
        font-weight: 600;
        font-size: 0.9375rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }
    
    .alert {
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    @media (max-width: 768px) {
        .productos-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .productos-header .btn-primary {
            width: 100%;
        }
        
        .filters-card > div {
            grid-template-columns: 1fr !important;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            flex-direction: column;
        }
        
        .modal-footer .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }
    }
</style>

<div class="container">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="productos-header">
        <h1>
            <i class="fas fa-box-open"></i> Gestión de Productos
        </h1>
        <button onclick="openModal('modal-nuevo-producto')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Producto
        </button>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px;">
            <input type="text" id="search-productos" class="form-control" placeholder="Buscar productos por nombre, código, marca..." data-table-search="table-productos">
            
            <select id="filter-almacen" class="form-control" onchange="window.location.href='?almacen=' + this.value">
                <option value="">Todos los almacenes</option>
                <?php 
                // Obtener almacenes filtrados por empresa para el filtro
                $almacenes_filter_query = "SELECT a.*, b.nombre as bodega_nombre FROM almacenes a LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega";
                $almacenes_filter_params = [];
                $almacenes_filter_types = '';
                // Agregar filtro por empresa si la columna existe
                if ($has_empresa_column_almacenes) {
                    if ($user_empresa_id !== null) {
                        $almacenes_filter_query .= " WHERE a.id_empresa = ?";
                        $almacenes_filter_params[] = $user_empresa_id;
                        $almacenes_filter_types = 'i';
                    }
                    // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
                }
                $almacenes_filter_query .= " ORDER BY a.nombre";
                if (!empty($almacenes_filter_params)) {
                    $almacenes_filter_stmt = $conn->prepare($almacenes_filter_query);
                    $almacenes_filter_stmt->bind_param($almacenes_filter_types, ...$almacenes_filter_params);
                    $almacenes_filter_stmt->execute();
                    $almacenes_filter = $almacenes_filter_stmt->get_result();
                } else {
                    $almacenes_filter = $conn->query($almacenes_filter_query);
                }
                while ($a = $almacenes_filter->fetch_assoc()): 
                ?>
                    <option value="<?php echo $a['id_almacen']; ?>" <?php echo $filter_almacen == $a['id_almacen'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['nombre']); ?> (<?php echo htmlspecialchars($a['bodega_nombre']); ?>)
                    </option>
                <?php endwhile; ?>
            </select>
            
            <select id="filter-categoria" class="form-control" onchange="window.location.href='?categoria=' + this.value">
                <option value="">Todas las categorías</option>
                <?php 
                $categorias_query = "SELECT valor FROM catalogos WHERE modulo='productos' AND campo='categoria' AND activo=1";
                if ($has_empresa_column_catalogos && $user_empresa_id !== null) {
                    $categorias_query .= " AND id_empresa = " . intval($user_empresa_id);
                }
                $categorias_query .= " ORDER BY orden, valor";
                $categorias_filter = $conn->query($categorias_query);
                while ($c = $categorias_filter->fetch_assoc()): 
                ?>
                    <option value="<?php echo htmlspecialchars($c['valor']); ?>" <?php echo $filter_categoria == $c['valor'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['valor']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- Tabla de Productos -->
    <div class="table-container">
        <table id="table-productos">
            <thead>
                <tr>
                    <th data-sortable>Código</th>
                    <th data-sortable>Producto</th>
                    <th class="hide-mobile">Categoría</th>
                    <th class="hide-mobile">Marca/Modelo</th>
                    <th>Almacén</th>
                    <th data-sortable>Cantidad</th>
                    <th class="hide-mobile">Precio Unit.</th>
                    <th class="hide-mobile">Valor Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($productos->num_rows > 0): ?>
                    <?php while ($producto = $productos->fetch_assoc()): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($producto['codigo_producto']); ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars($producto['nombre_producto']); ?></strong>
                            <?php if ($producto['cantidad'] <= $producto['stock_minimo'] && $producto['stock_minimo'] > 0): ?>
                                <br><small class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Stock bajo</small>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile"><?php echo htmlspecialchars($producto['categoria'] ?? 'N/A'); ?></td>
                        <td class="hide-mobile">
                            <?php echo htmlspecialchars($producto['marca'] ?? 'N/A'); ?>
                            <?php if ($producto['modelo']): ?>
                                <br><small><?php echo htmlspecialchars($producto['modelo']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($producto['almacen_codigo']); ?></small>
                            <br><?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                        </td>
                        <td>
                            <strong><?php echo $producto['cantidad']; ?></strong> <?php echo $producto['unidad']; ?>
                        </td>
                        <td class="hide-mobile">$<?php echo number_format($producto['precio_unitario'] ?? 0, 2); ?></td>
                        <td class="hide-mobile"><strong>$<?php 
                            // Calcular valor total en tiempo real: cantidad * precio_unitario
                            $cantidad = floatval($producto['cantidad'] ?? 0);
                            $precio = floatval($producto['precio_unitario'] ?? 0);
                            $valor_calculado = $cantidad * $precio;
                            echo number_format($valor_calculado, 2); 
                        ?></strong></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $producto['estado_producto'] == 'Disponible' ? 'success' : 
                                    ($producto['estado_producto'] == 'Reservado' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo $producto['estado_producto']; ?>
                            </span>
                        </td>
                        <td>
                            <button onclick='verDetalles(<?php echo json_encode($producto); ?>)' class="btn btn-sm btn-secondary" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick='editarProducto(<?php echo json_encode($producto); ?>)' class="btn btn-sm btn-info" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick='ajustarCantidad(<?php echo json_encode($producto); ?>)' class="btn btn-sm btn-warning" title="Ajustar cantidad" style="background-color: #f59e0b; color: white; border-color: #f59e0b;">
                                <i class="fas fa-adjust"></i>
                            </button>
                            <button onclick="confirmarEliminar(<?php echo $producto['id_producto']; ?>)" class="btn btn-sm btn-danger" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">No hay productos registrados</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nuevo Producto -->
<div class="modal-overlay" id="modal-nuevo-producto">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-box-open"></i> Nuevo Producto</h3>
            <button class="modal-close" onclick="closeModal('modal-nuevo-producto')" aria-label="Cerrar">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="lote" value="">
            <input type="hidden" name="stock_maximo" value="0">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Nombre del Producto *</label>
                        <input type="text" name="nombre_producto" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Código del Producto *</label>
                        <input type="text" name="codigo_producto" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Categoría</label>
                        <select name="categoria" class="form-control">
                            <option value="">Seleccione una categoría</option>
                            <?php 
                            $cats_query = "SELECT valor FROM catalogos WHERE modulo='productos' AND campo='categoria' AND activo=1";
                            if ($has_empresa_column_catalogos && $user_empresa_id !== null) {
                                $cats_query .= " AND id_empresa = " . intval($user_empresa_id);
                            }
                            $cats_query .= " ORDER BY orden, valor";
                            $cats = $conn->query($cats_query);
                            while ($c = $cats->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($c['valor']); ?>"><?php echo htmlspecialchars($c['valor']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Marca</label>
                        <input type="text" name="marca" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="modelo" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Número de Serie</label>
                        <input type="text" name="numero_serie" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Almacén *</label>
                        <select name="id_almacen" class="form-control" required>
                            <option value="">Seleccione un almacén</option>
                            <?php 
                            // Obtener almacenes filtrados por empresa para crear producto
                            $alms_query = "SELECT a.*, b.nombre as bodega_nombre FROM almacenes a LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega WHERE a.estado_almacen IN ('Disponible', 'Ocupado')";
                            $alms_params = [];
                            $alms_types = '';
                            // Agregar filtro por empresa si la columna existe
                            if ($has_empresa_column_almacenes) {
                                if ($user_empresa_id !== null) {
                                    $alms_query .= " AND a.id_empresa = ?";
                                    $alms_params[] = $user_empresa_id;
                                    $alms_types = 'i';
                                }
                                // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
                            }
                            $alms_query .= " ORDER BY a.nombre";
                            if (!empty($alms_params)) {
                                $alms_stmt = $conn->prepare($alms_query);
                                $alms_stmt->bind_param($alms_types, ...$alms_params);
                                $alms_stmt->execute();
                                $alms = $alms_stmt->get_result();
                            } else {
                                $alms = $conn->query($alms_query);
                            }
                            while ($a = $alms->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $a['id_almacen']; ?>">
                                    <?php echo htmlspecialchars($a['nombre']); ?> - <?php echo htmlspecialchars($a['bodega_nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="cantidad" class="form-control" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unidad</label>
                        <select name="unidad" class="form-control">
                            <option value="piezas">Piezas</option>
                            <option value="kg">Kilogramos</option>
                            <option value="litros">Litros</option>
                            <option value="cajas">Cajas</option>
                            <!-- <option value="pallets">Pallets</option> -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Precio Unitario</label>
                        <input type="number" step="0.01" name="precio_unitario" class="form-control" min="0" value="0">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" class="form-control" min="0" value="0">
                    </div>
                    
                    <!-- <div class="form-group">
                        <label class="form-label">Stock Máximo</label>
                        <input type="number" name="stock_maximo" class="form-control" min="0" value="0">
                    </div> -->
                    
                    <div class="form-group">
                        <label class="form-label">Estado *</label>
                        <select name="estado_producto" class="form-control" required>
                            <?php
                            // Sin filtro por empresa - mostrar todos los estados
                            $estados_producto_query_sql = "SELECT valor FROM catalogos WHERE modulo='productos' AND campo='estado_producto' AND activo=1 ORDER BY orden, valor";
                            $estados_producto_query = $conn->query($estados_producto_query_sql);
                            
                            // Verificar cuántos estados hay
                            $count_estados = $estados_producto_query->num_rows;
                            
                            if ($count_estados > 0):
                                $first = true;
                                while ($est = $estados_producto_query->fetch_assoc()):
                            ?>
                                    <option value="<?php echo htmlspecialchars($est['valor']); ?>" <?php echo $first ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['valor']); ?>
                                    </option>
                            <?php 
                                    $first = false;
                                endwhile;
                            else:
                                // Si no hay estados en catálogo, usar valores por defecto
                            ?>
                                <option value="Disponible" selected>Disponible</option>
                                <option value="Reservado">Reservado</option>
                                <option value="Entregado">Entregado</option>
                                <option value="Dañado">Dañado</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($count_estados == 0): ?>
                            <small style="color: #ef4444;">⚠️ No hay estados en catálogo. Usando valores por defecto. <a href="../configuracion/configuracion.php">Ir a Configuración</a></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Ubicación Exacta</label>
                        <input type="text" name="ubicacion_exacta" class="form-control" placeholder="Ej: Rack A-3-5">
                    </div>
                    
                    <!-- <div class="form-group">
                        <label class="form-label">Lote</label>
                        <input type="text" name="lote" class="form-control">
                    </div> -->
                    
                    <div class="form-group">
                        <label class="form-label">Proveedor</label>
                        <input type="text" name="proveedor" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Ingreso *</label>
                        <input type="date" name="fecha_ingreso" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input type="date" name="fecha_vencimiento" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nuevo-producto')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Producto -->
<div class="modal-overlay" id="modal-editar-producto">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Producto</h3>
            <button class="modal-close" onclick="closeModal('modal-editar-producto')" aria-label="Cerrar">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_producto" id="edit_id_producto">
            <input type="hidden" name="lote" id="edit_lote" value="">
            <input type="hidden" name="stock_maximo" id="edit_stock_maximo" value="0">
            <div class="modal-body">
                <!-- Campos similares al formulario de creación con IDs edit_* -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Nombre del Producto *</label>
                        <input type="text" name="nombre_producto" id="edit_nombre_producto" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Código del Producto *</label>
                        <input type="text" name="codigo_producto" id="edit_codigo_producto" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Categoría</label>
                        <select name="categoria" id="edit_categoria" class="form-control">
                            <option value="">Seleccione una categoría</option>
                            <?php 
                            $cats_edit_query = "SELECT valor FROM catalogos WHERE modulo='productos' AND campo='categoria' AND activo=1";
                            if ($has_empresa_column_catalogos && $user_empresa_id !== null) {
                                $cats_edit_query .= " AND id_empresa = " . intval($user_empresa_id);
                            }
                            $cats_edit_query .= " ORDER BY orden, valor";
                            $cats_edit = $conn->query($cats_edit_query);
                            while ($c = $cats_edit->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($c['valor']); ?>"><?php echo htmlspecialchars($c['valor']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Marca</label>
                        <input type="text" name="marca" id="edit_marca" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="modelo" id="edit_modelo" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Número de Serie</label>
                        <input type="text" name="numero_serie" id="edit_numero_serie" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Almacén *</label>
                        <select name="id_almacen" id="edit_id_almacen" class="form-control" required>
                            <?php 
                            // Obtener almacenes filtrados por empresa para editar producto
                            $alms2_query = "SELECT a.*, b.nombre as bodega_nombre FROM almacenes a LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega WHERE a.estado_almacen IN ('Disponible', 'Ocupado')";
                            $alms2_params = [];
                            $alms2_types = '';
                            // Agregar filtro por empresa si la columna existe
                            if ($has_empresa_column_almacenes) {
                                if ($user_empresa_id !== null) {
                                    $alms2_query .= " AND a.id_empresa = ?";
                                    $alms2_params[] = $user_empresa_id;
                                    $alms2_types = 'i';
                                }
                                // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
                            }
                            $alms2_query .= " ORDER BY a.nombre";
                            if (!empty($alms2_params)) {
                                $alms2_stmt = $conn->prepare($alms2_query);
                                $alms2_stmt->bind_param($alms2_types, ...$alms2_params);
                                $alms2_stmt->execute();
                                $alms2 = $alms2_stmt->get_result();
                            } else {
                                $alms2 = $conn->query($alms2_query);
                            }
                            while ($a = $alms2->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $a['id_almacen']; ?>">
                                    <?php echo htmlspecialchars($a['nombre']); ?> - <?php echo htmlspecialchars($a['bodega_nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="cantidad" id="edit_cantidad" class="form-control" required disabled>
                        <small class="form-text text-muted" style="display: block; margin-top: 0.25rem; color: #6b7280;">
                            <i class="fas fa-info-circle"></i> La cantidad no se puede editar aquí.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unidad</label>
                        <select name="unidad" id="edit_unidad" class="form-control">
                            <option value="piezas">Piezas</option>
                            <option value="kg">Kilogramos</option>
                            <option value="litros">Litros</option>
                            <option value="cajas">Cajas</option>
                            <option value="pallets">Pallets</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Precio Unitario</label>
                        <input type="number" step="0.01" name="precio_unitario" id="edit_precio_unitario" class="form-control" min="0" value="0">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" id="edit_stock_minimo" class="form-control" min="0" value="0">
                    </div>
                    
                    <!-- <div class="form-group">
                        <label class="form-label">Stock Máximo</label>
                        <input type="number" name="stock_maximo" id="edit_stock_maximo" class="form-control">
                    </div> -->
                    
                    <div class="form-group">
                        <label class="form-label">Estado *</label>
                        <select name="estado_producto" id="edit_estado_producto" class="form-control" required>
                            <?php
                            // Sin filtro por empresa - mostrar todos los estados
                            $estados_producto_edit_query_sql = "SELECT valor FROM catalogos WHERE modulo='productos' AND campo='estado_producto' AND activo=1 ORDER BY orden, valor";
                            $estados_producto_edit_query = $conn->query($estados_producto_edit_query_sql);
                            
                            $count_estados_edit = $estados_producto_edit_query->num_rows;
                            
                            if ($count_estados_edit > 0):
                                $first_edit = true;
                                while ($est = $estados_producto_edit_query->fetch_assoc()):
                            ?>
                                    <option value="<?php echo htmlspecialchars($est['valor']); ?>" <?php echo $first_edit ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['valor']); ?>
                                    </option>
                            <?php 
                                    $first_edit = false;
                                endwhile;
                            else:
                                // Si no hay estados en catálogo, usar valores por defecto
                            ?>
                                <option value="Disponible" selected>Disponible</option>
                                <option value="Reservado">Reservado</option>
                                <option value="Entregado">Entregado</option>
                                <option value="Dañado">Dañado</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($count_estados_edit == 0): ?>
                            <small style="color: #ef4444;">⚠️ No hay estados en catálogo. Usando valores por defecto. <a href="../configuracion/configuracion.php">Ir a Configuración</a></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Ubicación Exacta</label>
                        <input type="text" name="ubicacion_exacta" id="edit_ubicacion_exacta" class="form-control">
                    </div>
                    
                    <!-- <div class="form-group">
                        <label class="form-label">Lote</label>
                        <input type="text" name="lote" id="edit_lote" class="form-control">
                    </div> -->
                    
                    <div class="form-group">
                        <label class="form-label">Proveedor</label>
                        <input type="text" name="proveedor" id="edit_proveedor" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Ingreso *</label>
                        <input type="date" name="fecha_ingreso" id="edit_fecha_ingreso" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input type="date" name="fecha_vencimiento" id="edit_fecha_vencimiento" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" id="edit_observaciones" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-editar-producto')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver Detalles -->
<div class="modal-overlay" id="modal-detalles-producto">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalles del Producto</h3>
            <button class="modal-close" onclick="closeModal('modal-detalles-producto')" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body" id="detalles-content">
            <!-- Contenido dinámico -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-detalles-producto')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Ajuste de Producto -->
<div class="modal-overlay" id="modal-ajustar-producto">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-adjust"></i> Ajuste de Producto</h3>
            <button class="modal-close" onclick="closeModal('modal-ajustar-producto')" aria-label="Cerrar">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="ajustar_cantidad">
            <input type="hidden" name="id_producto" id="ajustar_id_producto">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Producto</label>
                    <input type="text" id="ajustar_nombre_producto" class="form-control" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Código</label>
                    <input type="text" id="ajustar_codigo_producto" class="form-control" disabled>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Cantidad Actual</label>
                        <input type="number" id="ajustar_cantidad_actual" class="form-control" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nueva Cantidad *</label>
                        <input type="number" name="nueva_cantidad" id="ajustar_nueva_cantidad" class="form-control" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Motivo del Ajuste *</label>
                    <textarea name="motivo" id="ajustar_motivo" class="form-control" rows="3" placeholder="Ej: Corrección de inventario, pérdida, sobrante, etc." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-ajustar-producto')">Cancelar</button>
                <button type="submit" class="btn btn-warning" style="background-color: #f59e0b; color: white; border-color: #f59e0b;">
                    <i class="fas fa-save"></i> Guardar Ajuste
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Form para eliminar -->
<form id="form-eliminar" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_producto" id="delete_id_producto">
</form>

<script src="../../js/main.js"></script>
<script>
// Asegurar que las funciones de modal estén disponibles
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevenir scroll del body
    } else {
        console.error('Modal no encontrado: ' + modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // Restaurar scroll del body
    }
}

// Cerrar modal al hacer clic fuera de él
document.addEventListener('DOMContentLoaded', function() {
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modals.forEach(modal => {
                if (modal.classList.contains('active')) {
                    closeModal(modal.id);
                }
            });
        }
    });
});

function editarProducto(producto) {
    document.getElementById('edit_id_producto').value = producto.id_producto || '';
    document.getElementById('edit_nombre_producto').value = producto.nombre_producto || '';
    document.getElementById('edit_codigo_producto').value = producto.codigo_producto || '';
    document.getElementById('edit_descripcion').value = producto.descripcion || '';
    
    // Si el valor actual de categoría no existe en las opciones, agregarlo temporalmente
    const selectCategoria = document.getElementById('edit_categoria');
    if (selectCategoria) {
        let optionExistsCategoria = false;
        for (let option of selectCategoria.options) {
            if (option.value === producto.categoria) {
                optionExistsCategoria = true;
                break;
            }
        }
        if (!optionExistsCategoria && producto.categoria) {
            const newOption = document.createElement('option');
            newOption.value = producto.categoria;
            newOption.text = producto.categoria + ' (Inactivo)';
            selectCategoria.add(newOption);
        }
        selectCategoria.value = producto.categoria || '';
    }
    
    document.getElementById('edit_marca').value = producto.marca || '';
    document.getElementById('edit_modelo').value = producto.modelo || '';
    document.getElementById('edit_numero_serie').value = producto.numero_serie || '';
    document.getElementById('edit_id_almacen').value = producto.id_almacen || '';
    document.getElementById('edit_cantidad').value = producto.cantidad || 0;
    document.getElementById('edit_unidad').value = producto.unidad || 'piezas';
    document.getElementById('edit_precio_unitario').value = producto.precio_unitario || '';
    document.getElementById('edit_stock_minimo').value = producto.stock_minimo || 0;
    document.getElementById('edit_stock_maximo').value = producto.stock_maximo || 0;
    
    // Si el valor actual de estado no existe en las opciones, agregarlo temporalmente
    const selectEstado = document.getElementById('edit_estado_producto');
    if (selectEstado) {
        let optionExistsEstado = false;
        for (let option of selectEstado.options) {
            if (option.value === producto.estado_producto) {
                optionExistsEstado = true;
                break;
            }
        }
        if (!optionExistsEstado && producto.estado_producto) {
            const newOption = document.createElement('option');
            newOption.value = producto.estado_producto;
            newOption.text = producto.estado_producto + ' (Inactivo)';
            selectEstado.add(newOption);
        }
        selectEstado.value = producto.estado_producto || '';
    }
    
    document.getElementById('edit_ubicacion_exacta').value = producto.ubicacion_exacta || '';
    document.getElementById('edit_lote').value = producto.lote || '';
    document.getElementById('edit_proveedor').value = producto.proveedor || '';
    document.getElementById('edit_fecha_ingreso').value = producto.fecha_ingreso || '';
    document.getElementById('edit_fecha_vencimiento').value = producto.fecha_vencimiento || '';
    document.getElementById('edit_observaciones').value = producto.observaciones || '';
    
    // Abrir modal después de un pequeño delay para asegurar que los valores se establezcan
    setTimeout(function() {
        openModal('modal-editar-producto');
    }, 50);
}

function verDetalles(producto) {
    const content = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <strong>Código:</strong><br>
                <code>${producto.codigo_producto || 'N/A'}</code>
            </div>
            <div>
                <strong>Nombre:</strong><br>
                ${producto.nombre_producto || 'N/A'}
            </div>
            <div>
                <strong>Categoría:</strong><br>
                ${producto.categoria || 'N/A'}
            </div>
            <div>
                <strong>Marca:</strong><br>
                ${producto.marca || 'N/A'}
            </div>
            <div>
                <strong>Modelo:</strong><br>
                ${producto.modelo || 'N/A'}
            </div>
            <div>
                <strong>Número de Serie:</strong><br>
                ${producto.numero_serie || 'N/A'}
            </div>
            <div>
                <strong>Almacén:</strong><br>
                ${producto.almacen_nombre || 'N/A'}
            </div>
            <div>
                <strong>Bodega:</strong><br>
                ${producto.bodega_nombre || 'N/A'}
            </div>
            <div>
                <strong>Cantidad:</strong><br>
                ${producto.cantidad || 0} ${producto.unidad || 'piezas'}
            </div>
            <div>
                <strong>Precio Unitario:</strong><br>
                $${parseFloat(producto.precio_unitario || 0).toFixed(2)}
            </div>
            <div>
                <strong>Valor Total:</strong><br>
                <strong>$${(parseFloat(producto.cantidad || 0) * parseFloat(producto.precio_unitario || 0)).toFixed(2)}</strong>
            </div>
            <div>
                <strong>Estado:</strong><br>
                <span class="badge badge-success">${producto.estado_producto || 'N/A'}</span>
            </div>
        </div>
        <hr style="margin: 20px 0;">
        <div>
            <strong>Descripción:</strong><br>
            ${producto.descripcion || 'Sin descripción'}
        </div>
        ${producto.observaciones ? `
        <div style="margin-top: 15px;">
            <strong>Observaciones:</strong><br>
            ${producto.observaciones}
        </div>
        ` : ''}
    `;
    
    document.getElementById('detalles-content').innerHTML = content;
    openModal('modal-detalles-producto');
}

function ajustarCantidad(producto) {
    document.getElementById('ajustar_id_producto').value = producto.id_producto || '';
    document.getElementById('ajustar_nombre_producto').value = producto.nombre_producto || '';
    document.getElementById('ajustar_codigo_producto').value = producto.codigo_producto || '';
    document.getElementById('ajustar_cantidad_actual').value = producto.cantidad || 0;
    document.getElementById('ajustar_nueva_cantidad').value = producto.cantidad || 0;
    document.getElementById('ajustar_motivo').value = '';
    
    // Abrir modal después de un pequeño delay
    setTimeout(function() {
        openModal('modal-ajustar-producto');
    }, 50);
}

function confirmarEliminar(id) {
    if (confirm('¿Está seguro de que desea eliminar este producto? Esta acción no se puede deshacer.')) {
        document.getElementById('delete_id_producto').value = id;
        document.getElementById('form-eliminar').submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

