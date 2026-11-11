# Plan de Implementación Completo: Sistema Multi-Empresa

## Resumen Ejecutivo

Este documento describe la implementación completa del sistema multi-empresa para los módulos de Tickets, Inventario y Almacén.

## Objetivos

1. ✅ Asignar usuarios a empresas específicas
2. ✅ Filtrar datos por empresa (usuarios solo ven su empresa)
3. ✅ Administradores ven todas las empresas
4. ✅ Configuración de email (SMTP/IMAP) por empresa
5. ✅ Identificación visual de empresa en interfaces

## Archivos Creados/Modificados

### Nuevos Archivos

1. `sql/multi_empresa_migration.sql` - Script de migración de base de datos
2. `includes/empresa_helper.php` - Funciones helper para multi-empresa
3. `tickets/modules/configuration/empresa_email_config.php` - Configuración de email por empresa
4. `tickets/conexion/get_empresa_email_config.php` - Funciones para obtener configuración de email
5. `GUIA_MULTI_EMPRESA.md` - Guía de uso del sistema
6. `IMPLEMENTACION_MULTI_EMPRESA.md` - Este documento

### Archivos Modificados

1. `auth.php` - Agregado include de empresa_helper.php
2. `index.php` - Modificado para incluir id_empresa en sesión

### Archivos Pendientes de Modificación

#### Módulo de Tickets
- `tickets/modules/tickets/tickets.php`
- `tickets/modules/tickets/new.php`
- `tickets/modules/tickets/edit.php`
- `tickets/modules/tickets/view.php`
- `tickets/modules/tickets/all_tickets.php`
- `tickets/modules/tickets/closed_tickets.php`
- `tickets/modules/requester/requester.php`
- `tickets/modules/requester/add.php`
- `tickets/modules/requester/edit.php`
- `tickets/modules/technicians/technicians.php`
- `tickets/modules/technicians/add.php`
- `tickets/modules/technicians/edit.php`
- `tickets/modules/reports/reports.php`
- `tickets/modules/outlook_emails.php` ⚠️ **CRÍTICO**

#### Módulo de Inventario
- `Inventario/crud/visualizar-equipos.php`
- `Inventario/crud/agregar-equipo.php`
- `Inventario/crud/editar-equipo.php`
- `Inventario/crud/actualizar-equipo.php`
- `Inventario/crud/visualizar-empresas.php`
- `Inventario/crud/agregar-empresa.php`
- `Inventario/crud/editar-empresa.php`
- `Inventario/crud/historial-actividades.php`
- `Inventario/crud/inicio.php`

#### Módulo de Almacén
- `almacen/modules/bodegas/bodegas.php`
- `almacen/modules/almacenes/almacenes.php`
- `almacen/modules/productos/productos.php`
- `almacen/modules/cartas/cartas-entrega.php`
- `almacen/modules/cartas/generar-carta.php`
- `almacen/modules/historial/historial.php`
- `almacen/modules/inicio/inicio.php`

#### Sistema General
- `gestion-usuarios.php` ⚠️ **IMPORTANTE** - Agregar campo de empresa

## Pasos de Implementación

### Fase 1: Base de Datos ✅

1. ✅ Crear script SQL de migración
2. ✅ Agregar campos `id_empresa` a todas las tablas
3. ✅ Crear tabla `empresa_email_config`
4. ⏳ Ejecutar script en base de datos de producción

### Fase 2: Funciones Helper ✅

1. ✅ Crear `includes/empresa_helper.php`
2. ✅ Modificar `auth.php` para incluir helper
3. ✅ Modificar `index.php` para cargar empresa en sesión

### Fase 3: Configuración de Email ✅

1. ✅ Crear interfaz de configuración de email por empresa
2. ✅ Crear funciones para obtener configuración de email
3. ⏳ Modificar `outlook_emails.php` para usar configuración por empresa

### Fase 4: Gestión de Usuarios ⏳

1. ⏳ Modificar `gestion-usuarios.php` para asignar empresa
2. ⏳ Agregar campo de empresa en formularios de usuario

### Fase 5: Módulo de Tickets ⏳

1. ⏳ Modificar todos los archivos de tickets para filtrar por empresa
2. ⏳ Agregar badges de empresa en interfaces
3. ⏳ Modificar `outlook_emails.php` para procesar por empresa

### Fase 6: Módulo de Inventario ⏳

1. ⏳ Modificar todos los archivos de inventario para filtrar por empresa
2. ⏳ Agregar badges de empresa en interfaces

### Fase 7: Módulo de Almacén ⏳

1. ⏳ Modificar todos los archivos de almacén para filtrar por empresa
2. ⏳ Agregar badges de empresa en interfaces

### Fase 8: Pruebas ⏳

1. ⏳ Probar con usuarios de diferentes empresas
2. ⏳ Verificar que admin ve todas las empresas
3. ⏳ Verificar procesamiento de emails por empresa
4. ⏳ Verificar filtros en todos los módulos

## Patrón de Modificación

Para cada archivo que lista datos, seguir este patrón:

```php
<?php
// 1. Incluir helpers
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

// 2. Construir consulta base
$query = "SELECT * FROM tabla WHERE condicion = ?";

// 3. Agregar filtro de empresa
$params = [];
$types = '';
$query = addEmpresaFilter($query, 'id_empresa', $params, $types);

// 4. Ejecutar consulta
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
```

Para crear/editar registros:

```php
<?php
// Al crear, asignar empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

$stmt = $conn->prepare("INSERT INTO tabla (campo1, campo2, id_empresa) VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $campo1, $campo2, $user_empresa_id);
$stmt->execute();
?>
```

## Prioridades

### Alta Prioridad ⚠️

1. **gestion-usuarios.php** - Necesario para asignar empresas a usuarios
2. **outlook_emails.php** - Crítico para procesamiento de emails por empresa
3. **tickets/modules/tickets/tickets.php** - Archivo principal de tickets

### Media Prioridad

4. Archivos de creación/edición en cada módulo
5. Archivos de listado principales

### Baja Prioridad

6. Archivos de reportes
7. Archivos de historial

## Notas Importantes

1. **Usuario Admin**: Debe tener `id_empresa = NULL` para ver todo
2. **Migración de Datos**: Los tickets existentes se asignan según requester
3. **Backup**: Hacer backup antes de ejecutar migración SQL
4. **Testing**: Probar en ambiente de desarrollo primero
5. **Documentación**: Actualizar documentación después de cambios

## Checklist de Implementación

- [ ] Ejecutar script SQL de migración
- [ ] Asignar empresas a usuarios existentes
- [ ] Modificar gestion-usuarios.php
- [ ] Modificar outlook_emails.php
- [ ] Modificar archivos principales de tickets
- [ ] Modificar archivos principales de inventario
- [ ] Modificar archivos principales de almacén
- [ ] Agregar badges de empresa en headers
- [ ] Probar con usuarios de diferentes empresas
- [ ] Verificar que admin ve todas las empresas
- [ ] Configurar email por empresa
- [ ] Probar procesamiento de emails
- [ ] Documentar cambios

## Soporte

Para dudas o problemas, consultar:
- `GUIA_MULTI_EMPRESA.md` - Guía de uso
- `includes/empresa_helper.php` - Funciones disponibles
- Ejemplos en este documento

