# Implementación Multi-Empresa: Módulo de Almacén

## Objetivo
Implementar el sistema multi-empresa en el módulo de Almacén para que todos los movimientos y datos se clasifiquen por la empresa del usuario que inició sesión (`empresas_usuarios`).

## Patrón de Implementación

### 1. Incluir Helpers
```php
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
```

### 2. Obtener Empresa del Usuario
```php
$user_empresa_id = getCurrentUserEmpresaId();
```

### 3. Filtrar Consultas SELECT
```php
$query = "SELECT * FROM tabla WHERE condicion = ?";
$params = [];
$types = '';
$query = addEmpresaFilter($query, 'id_empresa', $params, $types);
$query .= " ORDER BY campo";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
```

### 4. Asignar Empresa al Crear Registros
```php
$user_empresa_id = getCurrentUserEmpresaId();
$stmt = $conn->prepare("INSERT INTO tabla (campo1, campo2, id_empresa) VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $campo1, $campo2, $user_empresa_id);
$stmt->execute();
```

### 5. Verificar Permisos al Editar/Eliminar
```php
if ($has_empresa_column && $user_empresa_id !== null) {
    $verify_query = "SELECT id FROM tabla WHERE id = ? AND id_empresa = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("ii", $id, $user_empresa_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    if ($verify_result->num_rows === 0) {
        // No tiene permiso
    }
}
```

## Archivos a Modificar

### Archivos Principales
- [ ] `almacen/modules/bodegas/bodegas.php` - Gestión de bodegas con filtro por empresa
- [ ] `almacen/modules/almacenes/almacenes.php` - Gestión de almacenes con filtro por empresa
- [ ] `almacen/modules/productos/productos.php` - Gestión de productos con filtro por empresa
- [ ] `almacen/modules/inicio/inicio.php` - Dashboard con contadores filtrados por empresa
- [ ] `almacen/modules/historial/historial.php` - Historial filtrado por empresa

### Archivos de Cartas
- [x] `almacen/modules/cartas/cartas-entrega.php` - Listado de cartas filtrado por empresa
- [x] `almacen/modules/cartas/generar-carta.php` - Generar carta asignando empresa del usuario
- [x] `almacen/modules/cartas/procesar-carta.php` - Procesar carta con verificación de empresa y contador independiente por empresa
- [x] `almacen/modules/cartas/acciones-carta.php` - Acciones de carta con verificación de empresa
- [x] `almacen/modules/cartas/ver-carta-pdf.php` - Ver carta con verificación de empresa

### Archivos de Reportes
- [x] `almacen/modules/reportes/reportes.php` - Reportes filtrados por empresa

### Archivos de Configuración
- [x] `almacen/modules/configuracion/configuracion.php` - Configuración filtrada por empresa
- [x] `almacen/includes/get_catalogos.php` - API de catálogos filtrada por empresa

## Tablas de Base de Datos

### Tablas que necesitan `id_empresa` para filtrado por `empresas_usuarios`
- `bodegas` - Necesita `id_empresa` para filtrar bodegas por empresa
- `almacenes` - Necesita `id_empresa` para filtrar almacenes por empresa
- `productos_almacen` - Necesita `id_empresa` para filtrar productos por empresa
- `cartas_entrega` - Necesita `id_empresa` para filtrar cartas por empresa
- `historial_movimientos` - Necesita `id_empresa` para filtrar movimientos por empresa
- `catalogos` - Necesita `id_empresa` para filtrar catálogos por empresa (cada empresa tiene sus propios catálogos)

## Notas Importantes

1. **Jerarquía de Datos**: 
   - Bodegas → Almacenes → Productos
   - Al filtrar productos, también se deben filtrar los almacenes y bodegas relacionados

2. **Usuario Admin**: Los administradores (con `id_empresa = NULL`) pueden ver todos los registros de todas las empresas.

3. **Compatibilidad**: Los módulos deben seguir funcionando igual, solo con el filtrado adicional por empresa.

4. **Cartas de Entrega**: El contador de cartas también debe ser independiente por empresa (similar a los reportes de Inventario).

## Progreso de Implementación

**Fecha de inicio**: 2025-12-11
**Última actualización**: 2025-12-15

### Completados: 13/13 archivos
- [x] `almacen/modules/bodegas/bodegas.php` - Modificado para filtrar por empresa del usuario
- [x] `almacen/modules/almacenes/almacenes.php` - Modificado para filtrar por empresa del usuario
- [x] `almacen/modules/productos/productos.php` - Modificado para filtrar por empresa del usuario
- [x] `almacen/modules/inicio/inicio.php` - Modificado para filtrar estadísticas por empresa del usuario
- [x] `almacen/modules/historial/historial.php` - Modificado para filtrar historial por empresa del usuario
- [x] `almacen/modules/cartas/cartas-entrega.php` - Listado de cartas filtrado por empresa
- [x] `almacen/modules/cartas/generar-carta.php` - Generar carta asignando empresa del usuario, productos y almacenes filtrados
- [x] `almacen/modules/cartas/procesar-carta.php` - Procesar carta con verificación de empresa y contador independiente por empresa (`contador_cartas_almacen_empresa_X.txt`)
- [x] `almacen/modules/cartas/acciones-carta.php` - Acciones de carta con verificación de empresa
- [x] `almacen/modules/cartas/ver-carta-pdf.php` - Ver carta con verificación de empresa
- [x] `almacen/modules/reportes/reportes.php` - Reportes filtrados por empresa
- [x] `almacen/modules/configuracion/configuracion.php` - Configuración de catálogos filtrada por empresa
- [x] `almacen/includes/get_catalogos.php` - API de catálogos filtrada por empresa

### En Progreso: 0 archivos

### Pendientes: 0 archivos

## Cambios Implementados

### Sistema de Contadores de Cartas
- Los contadores de cartas ahora son independientes por empresa
- Archivo de contador: `contador_cartas_almacen_empresa_X.txt` (donde X es el `id_empresa_usuario`)
- Formato de número: `CA-0001`, `CA-0002`, etc. (independiente por empresa)

### Filtros Aplicados
- **Cartas**: Filtradas por `id_empresa` del usuario
- **Productos**: Solo se muestran productos de la empresa del usuario al generar cartas
- **Almacenes**: Solo se muestran almacenes de la empresa del usuario en filtros
- **Reportes**: Todas las estadísticas filtradas por empresa del usuario

## Scripts SQL Necesarios

### Verificar estructura de tablas
```sql
-- Verificar estructura de tablas
DESCRIBE bodegas;
DESCRIBE almacenes;
DESCRIBE productos_almacen;
DESCRIBE cartas_entrega;
DESCRIBE historial_movimientos;
```

### Agregar campo id_empresa si es necesario
```sql
-- Agregar id_empresa a bodegas
ALTER TABLE bodegas 
ADD COLUMN IF NOT EXISTS id_empresa INT(11) NULL DEFAULT NULL AFTER id_bodega;

ALTER TABLE bodegas 
ADD INDEX IF NOT EXISTS idx_bodegas_empresa (id_empresa);

-- Agregar id_empresa a almacenes
ALTER TABLE almacenes 
ADD COLUMN IF NOT EXISTS id_empresa INT(11) NULL DEFAULT NULL AFTER id_almacen;

ALTER TABLE almacenes 
ADD INDEX IF NOT EXISTS idx_almacenes_empresa (id_empresa);

-- Agregar id_empresa a productos_almacen
ALTER TABLE productos_almacen 
ADD COLUMN IF NOT EXISTS id_empresa INT(11) NULL DEFAULT NULL AFTER id_producto;

ALTER TABLE productos_almacen 
ADD INDEX IF NOT EXISTS idx_productos_almacen_empresa (id_empresa);

-- Agregar id_empresa a cartas_entrega
ALTER TABLE cartas_entrega 
ADD COLUMN IF NOT EXISTS id_empresa INT(11) NULL DEFAULT NULL AFTER id_carta;

ALTER TABLE cartas_entrega 
ADD INDEX IF NOT EXISTS idx_cartas_entrega_empresa (id_empresa);

-- Agregar id_empresa a historial_movimientos
ALTER TABLE historial_movimientos 
ADD COLUMN IF NOT EXISTS id_empresa INT(11) NULL DEFAULT NULL AFTER id_movimiento;

ALTER TABLE historial_movimientos 
ADD INDEX IF NOT EXISTS idx_historial_movimientos_empresa (id_empresa);

-- Agregar id_empresa a catalogos
ALTER TABLE catalogos 
ADD COLUMN IF NOT EXISTS id_empresa INT(11) NULL DEFAULT NULL AFTER id_catalogo;

ALTER TABLE catalogos 
ADD INDEX IF NOT EXISTS idx_catalogos_empresa (id_empresa);
```

## Pruebas a Realizar

1. **Usuario con empresa asignada**:
   - Ver solo bodegas/almacenes/productos de su empresa
   - Crear registros asignados a su empresa
   - No poder editar/eliminar registros de otras empresas
   - Ver solo historial de su empresa
   - Generar cartas asignadas a su empresa

2. **Usuario administrador**:
   - Ver todos los registros de todas las empresas
   - Poder editar/eliminar registros de cualquier empresa
   - Ver historial completo

3. **Cartas de Entrega**:
   - Las cartas generadas se asignan a la empresa del usuario
   - El contador de cartas es independiente por empresa
   - Validar que no se puedan generar cartas de otras empresas

4. **Reportes**:
   - Los reportes solo incluyen datos de la empresa del usuario
   - Los administradores pueden generar reportes de todas las empresas

## Referencias

- `plan/IMPLEMENTACION_INVENTARIO_MULTI_EMPRESA.md` - Implementación similar en Inventario
- `plan/GUIA_MULTI_EMPRESA.md` - Guía de uso del sistema
- `includes/empresa_helper.php` - Funciones helper disponibles

