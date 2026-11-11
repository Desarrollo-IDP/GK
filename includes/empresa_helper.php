<?php
/**
 * Funciones Helper para Sistema Multi-Empresa
 * Proporciona funciones para filtrar datos por empresa
 */

/**
 * Obtiene el ID de empresa del usuario actual
 * @return int|null ID de empresa o null si es admin
 */
function getCurrentUserEmpresaId() {
    if (!isLoggedIn()) {
        return null;
    }
    
    // Admin puede ver todo (id_empresa = null)
    if (isCurrentUserAdmin()) {
        return null;
    }
    
    return $_SESSION['user']['id_empresa'] ?? null;
}

/**
 * Verifica si el usuario puede acceder a una empresa específica
 * @param int|null $empresa_id ID de la empresa a verificar
 * @return bool True si puede acceder, false en caso contrario
 */
function canAccessEmpresa($empresa_id) {
    $user_empresa_id = getCurrentUserEmpresaId();
    
    // Admin puede acceder a todo
    if ($user_empresa_id === null) {
        return true;
    }
    
    // Usuario sin empresa asignada no puede acceder a nada
    if ($user_empresa_id === null && !isCurrentUserAdmin()) {
        return false;
    }
    
    return $user_empresa_id == $empresa_id;
}

/**
 * Agrega filtro de empresa a una consulta SQL
 * @param string $query Consulta SQL base
 * @param string $empresaColumn Nombre de la columna de empresa (default: 'id_empresa')
 * @param array $params Array de parámetros existentes (se modifica)
 * @param string $types Tipos de parámetros existentes (se modifica)
 * @return string Consulta SQL modificada
 */
function addEmpresaFilter($query, $empresaColumn = 'id_empresa', &$params = [], &$types = '') {
    $user_empresa_id = getCurrentUserEmpresaId();
    
    // Admin puede ver todo, no agregar filtro
    if ($user_empresa_id === null) {
        return $query;
    }
    
    // Agregar filtro WHERE o AND según corresponda
    if (stripos($query, 'WHERE') !== false) {
        $query .= " AND {$empresaColumn} = ?";
    } else {
        $query .= " WHERE {$empresaColumn} = ?";
    }
    
    $params[] = $user_empresa_id;
    $types .= 'i';
    
    return $query;
}

/**
 * Obtiene el nombre de la empresa del usuario actual
 * @return string|null Nombre de la empresa o null
 */
function getCurrentUserEmpresaName() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return $_SESSION['user']['empresa_nombre'] ?? null;
}

/**
 * Verifica si el usuario actual es admin (puede ver todas las empresas)
 * @return bool True si es admin, false en caso contrario
 */
function isMultiEmpresaAdmin() {
    return isCurrentUserAdmin() || getCurrentUserEmpresaId() === null;
}

/**
 * Obtiene el badge HTML para mostrar la empresa
 * @param int|null $empresa_id ID de la empresa
 * @param string|null $empresa_nombre Nombre de la empresa
 * @return string HTML del badge
 */
function getEmpresaBadge($empresa_id = null, $empresa_nombre = null) {
    if ($empresa_id === null || $empresa_nombre === null) {
        $empresa_id = getCurrentUserEmpresaId();
        $empresa_nombre = getCurrentUserEmpresaName();
    }
    
    if ($empresa_id === null) {
        return '<span class="badge badge-info">Todas las empresas</span>';
    }
    
    return '<span class="badge badge-primary" title="Empresa: ' . htmlspecialchars($empresa_nombre ?? '') . '">' . htmlspecialchars($empresa_nombre ?? '') . '</span>';
}

/**
 * Filtra un array de resultados por empresa
 * @param array $results Array de resultados
 * @param string $empresaColumn Nombre de la columna de empresa
 * @return array Array filtrado
 */
function filterResultsByEmpresa($results, $empresaColumn = 'id_empresa') {
    $user_empresa_id = getCurrentUserEmpresaId();
    
    // Admin puede ver todo
    if ($user_empresa_id === null) {
        return $results;
    }
    
    return array_filter($results, function($item) use ($user_empresa_id, $empresaColumn) {
        return isset($item[$empresaColumn]) && $item[$empresaColumn] == $user_empresa_id;
    });
}

/** Origen de contacto para módulo Tickets */
define('CONTACTO_ORIGEN_EMPRESAS', 'empresas');
/** Origen de contacto para módulo Inventario */
define('CONTACTO_ORIGEN_EMPRESAS_EQUIPOS', 'empresas_equipos');

/**
 * Indica si contactos_empresa tiene columna origen (migración opcional).
 */
function contactosEmpresaHasOrigenColumn($conn) {
    static $hasOrigen = null;
    if ($hasOrigen !== null) {
        return $hasOrigen;
    }
    $check = $conn->query("SHOW COLUMNS FROM contactos_empresa LIKE 'origen'");
    $hasOrigen = $check && $check->num_rows > 0;
    return $hasOrigen;
}

/**
 * LEFT JOIN seguro: un solo contacto por id_empresa (evita filas duplicadas).
 * Si existe columna origen, prioriza el del módulo y usa legacy (NULL) como fallback.
 *
 * @param mysqli $conn
 * @param string $origen CONTACTO_ORIGEN_EMPRESAS | CONTACTO_ORIGEN_EMPRESAS_EQUIPOS
 * @param string $alias Alias de la tabla de contactos en el JOIN
 * @return string Fragmento SQL "LEFT JOIN (...) alias ON ..."
 */
function contactosEmpresaLeftJoinSql($conn, $origen, $alias = 'c') {
    $origen = preg_replace('/[^a-z_]/', '', (string)$origen);
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias) ?: 'c';

    if (contactosEmpresaHasOrigenColumn($conn)) {
        $origenEsc = $conn->real_escape_string($origen);
        return "LEFT JOIN (
            SELECT c1.*
            FROM contactos_empresa c1
            INNER JOIN (
                SELECT id_empresa,
                       COALESCE(
                           MIN(CASE WHEN origen = '{$origenEsc}' THEN id_contacto END),
                           MIN(CASE WHEN origen IS NULL THEN id_contacto END)
                       ) AS mid
                FROM contactos_empresa
                GROUP BY id_empresa
            ) t ON c1.id_contacto = t.mid
        ) {$alias} ON e.id_empresa = {$alias}.id_empresa";
    }

    return "LEFT JOIN (
        SELECT c1.*
        FROM contactos_empresa c1
        INNER JOIN (
            SELECT id_empresa, MIN(id_contacto) AS mid
            FROM contactos_empresa
            GROUP BY id_empresa
        ) t ON c1.id_contacto = t.mid
    ) {$alias} ON e.id_empresa = {$alias}.id_empresa";
}

/**
 * Obtiene un contacto de empresa para el módulo indicado (sin duplicar lógica).
 */
function getContactoEmpresa($conn, $id_empresa, $origen) {
    $id_empresa = (int)$id_empresa;
    if ($id_empresa <= 0) {
        return null;
    }

    if (contactosEmpresaHasOrigenColumn($conn)) {
        $stmt = $conn->prepare("SELECT * FROM contactos_empresa WHERE id_empresa = ? AND origen = ? ORDER BY id_contacto ASC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("is", $id_empresa, $origen);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
        $stmt = $conn->prepare("SELECT * FROM contactos_empresa WHERE id_empresa = ? AND origen IS NULL ORDER BY id_contacto ASC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id_empresa);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        }
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM contactos_empresa WHERE id_empresa = ? ORDER BY id_contacto ASC LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $id_empresa);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Inserta o actualiza contacto sin crear filas duplicadas.
 * Con columna origen: separa Tickets e Inventario.
 * Sin columna: un solo contacto por id_empresa (compatible con producción actual).
 */
function upsertContactoEmpresa($conn, $id_empresa, $contacto, $telefono, $correo, $origen) {
    $id_empresa = (int)$id_empresa;
    $contacto = $contacto ?? '';
    $telefono = $telefono ?? '';
    $correo = $correo ?? '';

    if ($id_empresa <= 0) {
        return false;
    }
    if ($contacto === '' && $telefono === '' && $correo === '') {
        return true;
    }

    $existing = getContactoEmpresa($conn, $id_empresa, $origen);

    if ($existing) {
        if (contactosEmpresaHasOrigenColumn($conn)) {
            $stmt = $conn->prepare("UPDATE contactos_empresa SET contacto = ?, telefono = ?, correo = ?, origen = ? WHERE id_contacto = ?");
            if (!$stmt) {
                return false;
            }
            $id_contacto = (int)$existing['id_contacto'];
            $stmt->bind_param("ssssi", $contacto, $telefono, $correo, $origen, $id_contacto);
        } else {
            $stmt = $conn->prepare("UPDATE contactos_empresa SET contacto = ?, telefono = ?, correo = ? WHERE id_contacto = ?");
            if (!$stmt) {
                return false;
            }
            $id_contacto = (int)$existing['id_contacto'];
            $stmt->bind_param("sssi", $contacto, $telefono, $correo, $id_contacto);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    if (contactosEmpresaHasOrigenColumn($conn)) {
        $stmt = $conn->prepare("INSERT INTO contactos_empresa (id_empresa, contacto, telefono, correo, origen) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("issss", $id_empresa, $contacto, $telefono, $correo, $origen);
    } else {
        $stmt = $conn->prepare("INSERT INTO contactos_empresa (id_empresa, contacto, telefono, correo) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("isss", $id_empresa, $contacto, $telefono, $correo);
    }
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Borra contactos de forma segura para no afectar el otro módulo.
 * Con origen: solo borra los de ese origen (y legacy NULL si el id no existe en la otra tabla).
 * Sin origen: solo borra si el id_empresa no existe en la otra tabla.
 *
 * @param string $origen
 * @param string $otherTable 'empresas' | 'empresas_equipos'
 */
function deleteContactosEmpresaSafe($conn, $id_empresa, $origen, $otherTable) {
    $id_empresa = (int)$id_empresa;
    $otherTable = ($otherTable === 'empresas_equipos') ? 'empresas_equipos' : 'empresas';
    if ($id_empresa <= 0) {
        return false;
    }

    $existsOther = false;
    $checkOther = $conn->prepare("SELECT 1 FROM {$otherTable} WHERE id_empresa = ? LIMIT 1");
    if ($checkOther) {
        $checkOther->bind_param("i", $id_empresa);
        $checkOther->execute();
        $existsOther = $checkOther->get_result()->num_rows > 0;
        $checkOther->close();
    }

    if (contactosEmpresaHasOrigenColumn($conn)) {
        $stmt = $conn->prepare("DELETE FROM contactos_empresa WHERE id_empresa = ? AND origen = ?");
        if ($stmt) {
            $stmt->bind_param("is", $id_empresa, $origen);
            $stmt->execute();
            $stmt->close();
        }
        if (!$existsOther) {
            $stmt = $conn->prepare("DELETE FROM contactos_empresa WHERE id_empresa = ? AND origen IS NULL");
            if ($stmt) {
                $stmt->bind_param("i", $id_empresa);
                $stmt->execute();
                $stmt->close();
            }
        }
        return true;
    }

    if ($existsOther) {
        // Compartido entre módulos: no borrar para no afectar producción del otro apartado
        return true;
    }

    $stmt = $conn->prepare("DELETE FROM contactos_empresa WHERE id_empresa = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $id_empresa);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

