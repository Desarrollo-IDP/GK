<?php
/**
 * Configuración de base de datos para el sistema de membresías
 * 
 * Este archivo contiene la configuración de conexión a la base de datos
 * y funciones de utilidad para el manejo de datos.
 */

// Configuración de conexión a la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'secureitmxcom_membrecia');
define('DB_USER', 'secureitmxcom_membrecia');
define('DB_PASS', 'Becarios.2025');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');
define('DB_PORT', 3306);

// Configuración de PDO
define('PDO_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

/**
 * Obtener conexión a la base de datos
 * 
 * @return PDO
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, PDO_OPTIONS);
        } catch (PDOException $e) {
            error_log('Error de conexión a la base de datos: ' . $e->getMessage());
            throw new Exception('Error de conexión a la base de datos');
        }
    }
    
    return $pdo;
}

/**
 * Ejecutar una consulta SQL
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros de la consulta
 * @return PDOStatement|false
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Error al ejecutar consulta: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener un registro de la base de datos
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros de la consulta
 * @return array|false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

/**
 * Obtener múltiples registros de la base de datos
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros de la consulta
 * @return array|false
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : false;
}

/**
 * Insertar un registro en la base de datos
 * 
 * @param string $table Nombre de la tabla
 * @param array $data Datos a insertar
 * @return int|false ID del registro insertado
 */
function insertRecord($table, $data) {
    try {
        $pdo = getDatabaseConnection();
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Error al insertar registro: ' . $e->getMessage());
        return false;
    }
}

/**
 * Actualizar un registro en la base de datos
 * 
 * @param string $table Nombre de la tabla
 * @param array $data Datos a actualizar
 * @param string $where Condición WHERE
 * @param array $whereParams Parámetros de la condición WHERE
 * @return bool
 */
function updateRecord($table, $data, $where, $whereParams = []) {
    try {
        $pdo = getDatabaseConnection();
        
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $stmt = $pdo->prepare($sql);
        
        $params = array_merge($data, $whereParams);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log('Error al actualizar registro: ' . $e->getMessage());
        return false;
    }
}

/**
 * Eliminar un registro de la base de datos
 * 
 * @param string $table Nombre de la tabla
 * @param string $where Condición WHERE
 * @param array $params Parámetros de la condición WHERE
 * @return bool
 */
function deleteRecord($table, $where, $params = []) {
    try {
        $pdo = getDatabaseConnection();
        
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log('Error al eliminar registro: ' . $e->getMessage());
        return false;
    }
}

/**
 * Contar registros en una tabla
 * 
 * @param string $table Nombre de la tabla
 * @param string $where Condición WHERE (opcional)
 * @param array $params Parámetros de la condición WHERE
 * @return int
 */
function countRecords($table, $where = '', $params = []) {
    $sql = "SELECT COUNT(*) as total FROM {$table}";
    if (!empty($where)) {
        $sql .= " WHERE {$where}";
    }
    
    $result = fetchOne($sql, $params);
    return $result ? (int)$result['total'] : 0;
}

/**
 * Verificar si existe un registro
 * 
 * @param string $table Nombre de la tabla
 * @param string $where Condición WHERE
 * @param array $params Parámetros de la condición WHERE
 * @return bool
 */
function recordExists($table, $where, $params = []) {
    return countRecords($table, $where, $params) > 0;
}

/**
 * Obtener registros con paginación
 * 
 * @param string $sql Consulta SQL base
 * @param array $params Parámetros de la consulta
 * @param int $page Página actual
 * @param int $perPage Registros por página
 * @return array
 */
function fetchPaginated($sql, $params = [], $page = 1, $perPage = 20) {
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT {$perPage} OFFSET {$offset}";
    
    $records = fetchAll($sql, $params);
    $total = countRecords($sql, $params);
    
    return [
        'records' => $records ?: [],
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage)
    ];
}

/**
 * Iniciar una transacción
 * 
 * @return bool
 */
function beginTransaction() {
    try {
        $pdo = getDatabaseConnection();
        return $pdo->beginTransaction();
    } catch (PDOException $e) {
        error_log('Error al iniciar transacción: ' . $e->getMessage());
        return false;
    }
}

/**
 * Confirmar una transacción
 * 
 * @return bool
 */
function commitTransaction() {
    try {
        $pdo = getDatabaseConnection();
        return $pdo->commit();
    } catch (PDOException $e) {
        error_log('Error al confirmar transacción: ' . $e->getMessage());
        return false;
    }
}

/**
 * Revertir una transacción
 * 
 * @return bool
 */
function rollbackTransaction() {
    try {
        $pdo = getDatabaseConnection();
        return $pdo->rollback();
    } catch (PDOException $e) {
        error_log('Error al revertir transacción: ' . $e->getMessage());
        return false;
    }
}

/**
 * Escapar una cadena para uso en SQL
 * 
 * @param string $string Cadena a escapar
 * @return string
 */
function escapeString($string) {
    $pdo = getDatabaseConnection();
    return $pdo->quote($string);
}

/**
 * Validar conexión a la base de datos
 * 
 * @return bool
 */
function validateDatabaseConnection() {
    try {
        $pdo = getDatabaseConnection();
        $pdo->query('SELECT 1');
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtener información de la base de datos
 * 
 * @return array|false
 */
function getDatabaseInfo() {
    try {
        $pdo = getDatabaseConnection();
        
        $version = $pdo->query('SELECT VERSION() as version')->fetch();
        $tables = $pdo->query("SHOW TABLES")->fetchAll();
        
        return [
            'version' => $version['version'],
            'tables' => array_column($tables, 'Tables_in_' . DB_NAME),
            'charset' => $pdo->query('SELECT @@character_set_database as charset')->fetch()['charset'],
            'collation' => $pdo->query('SELECT @@collation_database as collation')->fetch()['collation']
        ];
    } catch (PDOException $e) {
        error_log('Error al obtener información de la base de datos: ' . $e->getMessage());
        return false;
    }
}

/**
 * Crear respaldo de la base de datos
 * 
 * @param string $backupPath Ruta donde guardar el respaldo
 * @return bool
 */
function createDatabaseBackup($backupPath) {
    try {
        $command = sprintf(
            'mysqldump -h %s -P %d -u %s -p%s %s > %s',
            DB_HOST,
            DB_PORT,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $backupPath
        );
        
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    } catch (Exception $e) {
        error_log('Error al crear respaldo de la base de datos: ' . $e->getMessage());
        return false;
    }
}

/**
 * Restaurar respaldo de la base de datos
 * 
 * @param string $backupPath Ruta del archivo de respaldo
 * @return bool
 */
function restoreDatabaseBackup($backupPath) {
    try {
        $command = sprintf(
            'mysql -h %s -P %d -u %s -p%s %s < %s',
            DB_HOST,
            DB_PORT,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $backupPath
        );
        
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    } catch (Exception $e) {
        error_log('Error al restaurar respaldo de la base de datos: ' . $e->getMessage());
        return false;
    }
}

/**
 * Optimizar tablas de la base de datos
 * 
 * @return bool
 */
function optimizeDatabase() {
    try {
        $pdo = getDatabaseConnection();
        $tables = $pdo->query("SHOW TABLES")->fetchAll();
        
        foreach ($tables as $table) {
            $tableName = $table['Tables_in_' . DB_NAME];
            $pdo->query("OPTIMIZE TABLE {$tableName}");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Error al optimizar la base de datos: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener estadísticas de la base de datos
 * 
 * @return array|false
 */
function getDatabaseStats() {
    try {
        $pdo = getDatabaseConnection();
        
        $stats = [];
        
        // Tamaño total de la base de datos
        $sizeQuery = $pdo->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb'
            FROM information_schema.tables 
            WHERE table_schema = '" . DB_NAME . "'
        ");
        $stats['total_size_mb'] = $sizeQuery->fetch()['size_mb'];
        
        // Número de tablas
        $tablesQuery = $pdo->query("SHOW TABLES");
        $stats['total_tables'] = $tablesQuery->rowCount();
        
        // Registros por tabla
        $stats['records_per_table'] = [];
        foreach ($tablesQuery->fetchAll() as $table) {
            $tableName = $table['Tables_in_' . DB_NAME];
            $countQuery = $pdo->query("SELECT COUNT(*) as count FROM {$tableName}");
            $stats['records_per_table'][$tableName] = $countQuery->fetch()['count'];
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log('Error al obtener estadísticas de la base de datos: ' . $e->getMessage());
        return false;
    }
} 