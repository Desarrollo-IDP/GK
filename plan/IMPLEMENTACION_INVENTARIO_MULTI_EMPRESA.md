# Implementación Multi-Empresa: Módulo de Inventario

## Objetivo
Implementar el sistema multi-empresa en el módulo de Inventario para que todos los movimientos y datos se clasifiquen por la empresa del usuario que inició sesión (`empresas_usuarios`).

## Patrón de Implementación

### 1. Incluir Helpers
```php
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
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
- [ ] `Inventario/crud/visualizar-equipos.php` - Listado de equipos con filtro por empresa
- [ ] `Inventario/crud/agregar-equipo.php` - Crear equipo asignando empresa del usuario
- [ ] `Inventario/crud/editar-equipo.php` - Editar equipo con verificación de empresa
- [ ] `Inventario/crud/actualizar-equipo.php` - Actualizar equipo con verificación de empresa
- [ ] `Inventario/crud/visualizar-empresas.php` - Listado de empresas (tabla empresas de inventario)
- [ ] `Inventario/crud/agregar-empresa.php` - Crear empresa asignando empresa del usuario
- [ ] `Inventario/crud/editar-empresa.php` - Editar empresa con verificación de empresa
- [ ] `Inventario/crud/inicio.php` - Dashboard con contadores filtrados por empresa
- [ ] `Inventario/crud/historial-actividades.php` - Historial filtrado por empresa

### Archivos de Reportes y Exportación
- [ ] `Inventario/crud/reporte.php` - Reportes filtrados por empresa
- [ ] `Inventario/crud/generar_reporte.php` - Generar reportes con filtro de empresa
- [ ] `Inventario/crud/exportar_excel.php` - Exportar datos filtrados por empresa

### Archivos de Importación
- [ ] `Inventario/crud/importar-equipos.php` - Importar equipos asignando empresa del usuario
- [ ] `Inventario/crud/importar.php` - Procesar importación con empresa del usuario

### Archivos API
- [ ] `Inventario/crud/api/equipos.php` - API de equipos con filtro por empresa
- [ ] `Inventario/crud/api/empresas.php` - API de empresas con filtro por empresa
- [ ] `Inventario/crud/api/update-equipo.php` - Actualizar equipo con verificación de empresa
- [ ] `Inventario/crud/api/update-empresa.php` - Actualizar empresa con verificación de empresa
- [ ] `Inventario/crud/api/delete-equipo.php` - Eliminar equipo con verificación de empresa
- [ ] `Inventario/crud/api/delete-empresa.php` - Eliminar empresa con verificación de empresa

### Archivos de Gestión de Usuarios (si aplica)
- [ ] `Inventario/crud/agregar-usuario.php` - Agregar usuario con empresa
- [ ] `Inventario/crud/editar-usuario.php` - Editar usuario con empresa
- [ ] `Inventario/crud/gestion-usuarios.php` - Gestión de usuarios con empresa
- [ ] `Inventario/crud/api/usuarios.php` - API de usuarios con empresa
- [ ] `Inventario/crud/api/update-usuario.php` - Actualizar usuario con empresa
- [ ] `Inventario/crud/api/delete-usuario.php` - Eliminar usuario con empresa

### Archivos de Visualización de Documentos
- [x] `Inventario/crud/ver-documento.php` - Ver documentos filtrados por empresa

## Tablas de Base de Datos

### Tablas que ya tienen `id_empresa`
- `equipos` - Ya tiene `id_empresa` (relacionado con tabla `empresas` de inventario)
- `empresas` - Tabla de empresas de inventario (tiene `id_empresa` como PK)

### Tablas que necesitan `id_empresa` para filtrado por `empresas_usuarios`
- `equipos` - Necesita relacionarse con `empresas_usuarios` (no solo con `empresas`)
- `historial_actividades` - Necesita `id_empresa` para filtrar actividades por empresa

## Notas Importantes

1. **Tabla `empresas`**: Esta es la tabla de empresas de inventario, diferente de `empresas_usuarios`. Los equipos tienen `id_empresa` que apunta a esta tabla.

2. **Relación con `empresas_usuarios`**: Necesitamos relacionar los equipos con `empresas_usuarios` del usuario logueado. Esto puede requerir:
   - Agregar un campo `id_empresa_usuario` a la tabla `equipos`, O
   - Relacionar a través de la tabla `empresas` si ya existe esa relación

3. **Usuario Admin**: Los administradores (con `id_empresa = NULL`) pueden ver todos los registros de todas las empresas.

4. **Compatibilidad**: Los módulos deben seguir funcionando igual, solo con el filtrado adicional por empresa.

## Progreso de Implementación

**Fecha de inicio**: 2025-12-08
**Última actualización**: 2025-12-08

### Completados: 21/25 archivos
- [x] `Inventario/crud/visualizar-equipos.php` - Modificado para filtrar por empresa del usuario
- [x] `Inventario/crud/agregar-equipo.php` - Modificado para asignar empresa del usuario al crear
- [x] `Inventario/crud/inicio.php` - Modificado para filtrar contadores por empresa
- [x] `Inventario/crud/editar-equipo.php` - Modificado para verificar empresa del usuario
- [x] `Inventario/crud/actualizar-equipo.php` - Modificado para verificar empresa antes de actualizar
- [x] `Inventario/crud/historial-actividades.php` - Modificado para filtrar por empresa
- [x] `Inventario/crud/visualizar-empresas.php` - Modificado para filtrar empresas por empresa del usuario
- [x] `Inventario/crud/agregar-empresa.php` - Modificado para asignar empresa del usuario al crear
- [x] `Inventario/crud/editar-empresa.php` - Modificado para verificar empresa antes de editar
- [x] `Inventario/crud/reporte.php` - Modificado para filtrar datos por empresa
- [x] `Inventario/crud/exportar_excel.php` - Modificado para verificar permisos al exportar
- [x] `Inventario/crud/importar-equipos.php` - Modificado para asignar empresa al importar
- [x] `Inventario/crud/api/equipos.php` - Modificado para asignar empresa al crear
- [x] `Inventario/crud/api/delete-equipo.php` - Modificado para verificar empresa antes de eliminar
- [x] `Inventario/crud/api/update-equipo.php` - Modificado para verificar empresa antes de actualizar
- [x] `Inventario/crud/api/empresas.php` - Modificado para filtrar empresas por empresa del usuario
- [x] `Inventario/crud/api/delete-empresa.php` - Modificado para verificar empresa antes de eliminar
- [x] `Inventario/crud/api/update-empresa.php` - Modificado para verificar empresa antes de actualizar
- [x] `log_activity.php` - Modificado para asignar id_empresa al registrar actividades
- [x] `Inventario/crud/generar_reporte.php` - Modificado para verificar permisos al generar reportes
- [x] `Inventario/crud/importar.php` - Modificado para asignar empresa al importar empresas
- [x] `Inventario/crud/ver-documento.php` - Modificado para verificar permisos antes de mostrar documentos

### En Progreso: 0 archivos

### Pendientes: 3 archivos (archivos menores o de gestión de usuarios)

## Cambios por Archivo

### `visualizar-equipos.php`
- [x] Incluir `empresa_helper.php`
- [ ] Agregar filtro por empresa en consulta SELECT
- [ ] Mostrar badge de empresa en interfaz (opcional para admin)

### `agregar-equipo.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Asignar `id_empresa` del usuario al crear equipo
- [ ] Validar que el usuario tenga empresa asignada

### `editar-equipo.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Verificar que el equipo pertenezca a la empresa del usuario
- [ ] Mostrar mensaje de error si no tiene permiso

### `actualizar-equipo.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Verificar que el equipo pertenezca a la empresa del usuario antes de actualizar
- [ ] Mantener `id_empresa` al actualizar

### `visualizar-empresas.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar empresas por empresa del usuario (si aplica)
- [ ] Mostrar badge de empresa en interfaz

### `agregar-empresa.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Asignar `id_empresa` del usuario al crear empresa de inventario
- [ ] Validar permisos

### `editar-empresa.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Verificar que la empresa pertenezca al usuario antes de editar
- [ ] Mantener `id_empresa` al actualizar

### `inicio.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar contadores por empresa del usuario
- [ ] Mostrar estadísticas solo de la empresa del usuario

### `historial-actividades.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar historial por empresa del usuario
- [ ] Agregar filtro de empresa en búsqueda

### `reporte.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar datos del reporte por empresa
- [ ] Mostrar solo equipos de la empresa del usuario

### `generar_reporte.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar datos por empresa al generar reporte
- [ ] Incluir información de empresa en reporte

### `exportar_excel.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar datos exportados por empresa
- [ ] Incluir columna de empresa en exportación

### `importar-equipos.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Asignar empresa del usuario a equipos importados
- [ ] Validar permisos de importación

### `importar.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Procesar importación con empresa del usuario
- [ ] Asignar empresa a cada registro importado

### `api/equipos.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar GET por empresa
- [ ] Asignar empresa al crear (POST)
- [ ] Verificar empresa al actualizar (PUT)
- [ ] Verificar empresa al eliminar (DELETE)

### `api/empresas.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Filtrar GET por empresa
- [ ] Asignar empresa al crear (POST)
- [ ] Verificar empresa al actualizar (PUT)
- [ ] Verificar empresa al eliminar (DELETE)

### `api/update-equipo.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Verificar que el equipo pertenezca a la empresa del usuario
- [ ] Mantener `id_empresa` al actualizar

### `api/update-empresa.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Verificar que la empresa pertenezca al usuario
- [ ] Mantener `id_empresa` al actualizar

### `api/delete-equipo.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Verificar que el equipo pertenezca a la empresa del usuario antes de eliminar
- [ ] Mostrar error si no tiene permiso

### `api/delete-empresa.php`
- [ ] Incluir `empresa_helper.php`
- [ ] Verificar que la empresa pertenezca al usuario antes de eliminar
- [ ] Mostrar error si no tiene permiso

## Scripts SQL Necesarios

### Verificar estructura de tablas
```sql
-- Verificar si equipos tiene id_empresa relacionado con empresas_usuarios
DESCRIBE equipos;

-- Verificar estructura de historial_actividades
DESCRIBE historial_actividades;
```

### Agregar campo si es necesario
```sql
-- Si equipos necesita relacionarse con empresas_usuarios además de empresas
ALTER TABLE equipos 
ADD COLUMN IF NOT EXISTS id_empresa_usuario INT(11) NULL DEFAULT NULL AFTER id_empresa;

-- Agregar índice
ALTER TABLE equipos 
ADD INDEX IF NOT EXISTS idx_equipos_empresa_usuario (id_empresa_usuario);

-- Agregar id_empresa a historial_actividades si no existe
ALTER TABLE historial_actividades 
ADD COLUMN IF NOT EXISTS id_empresa INT(11) NULL DEFAULT NULL AFTER id;

-- Agregar índice
ALTER TABLE historial_actividades 
ADD INDEX IF NOT EXISTS idx_historial_empresa (id_empresa);
```

## Pruebas a Realizar

1. **Usuario con empresa asignada**:
   - Ver solo equipos de su empresa
   - Crear equipos asignados a su empresa
   - No poder editar/eliminar equipos de otras empresas
   - Ver solo historial de su empresa

2. **Usuario administrador**:
   - Ver todos los equipos de todas las empresas
   - Poder editar/eliminar equipos de cualquier empresa
   - Ver historial completo

3. **Importación**:
   - Los equipos importados se asignan a la empresa del usuario
   - Validar que no se puedan importar equipos de otras empresas

4. **Reportes**:
   - Los reportes solo incluyen datos de la empresa del usuario
   - Los administradores pueden generar reportes de todas las empresas

## Referencias

- `plan/IMPLEMENTACION_MULTI_EMPRESA.md` - Plan general de implementación
- `plan/GUIA_MULTI_EMPRESA.md` - Guía de uso del sistema
- `includes/empresa_helper.php` - Funciones helper disponibles

