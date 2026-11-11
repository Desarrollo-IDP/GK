# Guía de Implementación: Sistema Multi-Empresa

## Descripción General

Este documento describe la implementación del sistema multi-empresa que permite:
- Asignar usuarios a empresas específicas
- Filtrar datos por empresa (usuarios solo ven datos de su empresa)
- Administradores pueden ver datos de todas las empresas
- Configuración de email (SMTP/IMAP) por empresa
- Identificación visual de la empresa en la interfaz

## Estructura de Base de Datos

### Tablas Modificadas

1. **usuarios**: Agregado campo `id_empresa`
2. **tickets**: Agregado campo `id_empresa`
3. **requesters**: Agregado campo `id_empresa`
4. **technicians**: Agregado campo `id_empresa`
5. **equipos**: Ya tiene `id_empresa`
6. **bodegas**: Agregado campo `id_empresa`
7. **almacenes**: Agregado campo `id_empresa`
8. **productos_almacen**: Agregado campo `id_empresa`
9. **cartas_entrega**: Agregado campo `id_empresa`
10. **historial_movimientos**: Agregado campo `id_empresa`
11. **historial_actividades**: Agregado campo `id_empresa`

### Nueva Tabla

**empresa_email_config**: Almacena configuración de email por empresa

## Pasos de Instalación

### 1. Ejecutar Script SQL

```bash
# Ejecutar el script de migración
mysql -u usuario -p nombre_base_datos < sql/multi_empresa_migration.sql
```

### 2. Asignar Empresas a Usuarios Existentes

Los usuarios existentes deben tener una empresa asignada. El usuario 'admin' debe tener `id_empresa = NULL` para ver todo.

```sql
-- Ejemplo: Asignar empresa a un usuario
UPDATE usuarios SET id_empresa = 1 WHERE nombre_usuario = 'usuario1';

-- El admin debe tener id_empresa = NULL
UPDATE usuarios SET id_empresa = NULL WHERE nombre_usuario = 'admin';
```

### 3. Configurar Email por Empresa

Acceder a: `/tickets/modules/configuration/empresa_email_config.php`

## Uso de Funciones Helper

### Incluir Helper en Archivos PHP

```php
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/empresa_helper.php';
checkAuth();
```

### Funciones Disponibles

#### `getCurrentUserEmpresaId()`
Obtiene el ID de empresa del usuario actual. Retorna `null` si es admin.

```php
$empresa_id = getCurrentUserEmpresaId();
if ($empresa_id === null) {
    // Usuario admin, puede ver todo
}
```

#### `canAccessEmpresa($empresa_id)`
Verifica si el usuario puede acceder a una empresa específica.

```php
if (canAccessEmpresa($empresa_id)) {
    // Usuario puede acceder
}
```

#### `addEmpresaFilter($query, $empresaColumn, &$params, &$types)`
Agrega filtro de empresa a una consulta SQL.

```php
$query = "SELECT * FROM tickets WHERE deleted = 0";
$params = [];
$types = '';
$query = addEmpresaFilter($query, 'id_empresa', $params, $types);

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
```

#### `getEmpresaBadge($empresa_id, $empresa_nombre)`
Genera HTML para mostrar badge de empresa.

```php
echo getEmpresaBadge(); // Muestra badge de la empresa del usuario actual
```

## Ejemplos de Implementación

### Ejemplo 1: Listado de Tickets

```php
<?php
require_once '../../includes/common.php';
require_once '../../conexion/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();

// Construir consulta con filtro de empresa
$query = "SELECT t.*, r.name as requester_name, tech.name as technician_name 
          FROM tickets t
          LEFT JOIN requesters r ON t.requester_id = r.id
          LEFT JOIN technicians tech ON t.technician_id = tech.id
          WHERE t.deleted = 0";
          
$params = [];
$types = '';
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
```

### Ejemplo 2: Crear Ticket con Empresa

```php
<?php
// Al crear un ticket, asignar la empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

$stmt = $conn->prepare("INSERT INTO tickets (subject, description, requester_id, id_empresa) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssii", $subject, $description, $requester_id, $user_empresa_id);
$stmt->execute();
?>
```

### Ejemplo 3: Listado de Equipos (Inventario)

```php
<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$query = "SELECT e.*, emp.nombre as empresa_nombre 
          FROM equipos e 
          LEFT JOIN empresas emp ON e.id_empresa = emp.id_empresa";
          
$params = [];
$types = '';
$query = addEmpresaFilter($query, 'e.id_empresa', $params, $types);
$query .= " ORDER BY e.fecha_registro DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
```

### Ejemplo 4: Mostrar Badge de Empresa en Interfaz

```php
<div class="header">
    <h1>Tickets</h1>
    <?php echo getEmpresaBadge(); ?>
</div>
```

### Ejemplo 5: Usar Configuración de Email por Empresa

```php
<?php
require_once __DIR__ . '/../conexion/get_empresa_email_config.php';
require_once __DIR__ . '/../conexion/db.php';

// Obtener configuración de email para una empresa
$empresa_id = 1; // ID de la empresa
$mail = createMailerForEmpresa($empresa_id, $conn);

if ($mail) {
    $mail->addAddress('destinatario@example.com');
    $mail->Subject = 'Asunto del correo';
    $mail->Body = 'Contenido del correo';
    $mail->send();
}
?>
```

## Modificación de outlook_emails.php

El archivo `outlook_emails.php` debe procesar emails por empresa. Necesita:

1. Identificar la empresa del email entrante
2. Usar la configuración IMAP de esa empresa
3. Asignar el ticket a la empresa correspondiente

```php
// Ejemplo de modificación necesaria
require_once(__DIR__ . '/../conexion/get_empresa_email_config.php');

// Obtener todas las empresas con configuración de email activa
$empresas_query = "SELECT eec.*, e.nombre as empresa_nombre 
                   FROM empresa_email_config eec
                   INNER JOIN empresas e ON eec.id_empresa = e.id_empresa
                   WHERE eec.activo = 1";
$empresas_result = $conn->query($empresas_query);

while ($empresa_config = $empresas_result->fetch_assoc()) {
    // Procesar emails para esta empresa
    $imap_config = getImapConfigForEmpresa($empresa_config['id_empresa'], $conn);
    
    // Conectar a IMAP con configuración de la empresa
    $mailbox = imap_open(
        "{" . $imap_config['host'] . ":" . $imap_config['port'] . $imap_config['encryption'] . $imap_config['options'] . "}" . $imap_config['folder'],
        $imap_config['username'],
        $imap_config['password']
    );
    
    // Procesar emails y crear tickets con id_empresa asignado
    // ...
}
```

## Archivos que Necesitan Modificación

### Módulo de Tickets

- `tickets/modules/tickets/tickets.php` - Listado de tickets
- `tickets/modules/tickets/new.php` - Crear ticket
- `tickets/modules/tickets/edit.php` - Editar ticket
- `tickets/modules/tickets/view.php` - Ver ticket
- `tickets/modules/tickets/all_tickets.php` - Todos los tickets
- `tickets/modules/tickets/closed_tickets.php` - Tickets cerrados
- `tickets/modules/requester/requester.php` - Listado de solicitantes
- `tickets/modules/requester/add.php` - Agregar solicitante
- `tickets/modules/technicians/technicians.php` - Listado de técnicos
- `tickets/modules/technicians/add.php` - Agregar técnico
- `tickets/modules/reports/reports.php` - Reportes

### Módulo de Inventario

- `Inventario/crud/visualizar-equipos.php` - Listado de equipos
- `Inventario/crud/agregar-equipo.php` - Agregar equipo
- `Inventario/crud/editar-equipo.php` - Editar equipo
- `Inventario/crud/visualizar-empresas.php` - Listado de empresas
- `Inventario/crud/historial-actividades.php` - Historial

### Módulo de Almacén

- `almacen/modules/bodegas/bodegas.php` - Listado de bodegas
- `almacen/modules/almacenes/almacenes.php` - Listado de almacenes
- `almacen/modules/productos/productos.php` - Listado de productos
- `almacen/modules/cartas/cartas-entrega.php` - Cartas de entrega
- `almacen/modules/historial/historial.php` - Historial

## Gestión de Usuarios

### Modificar gestion-usuarios.php

Agregar campo para seleccionar empresa al crear/editar usuario:

```php
<div class="form-group">
    <label for="id_empresa">Empresa</label>
    <select class="form-control" id="id_empresa" name="id_empresa">
        <option value="">Sin empresa (Admin)</option>
        <?php
        $empresas_query = "SELECT id_empresa, nombre FROM empresas ORDER BY nombre";
        $empresas_result = $conn->query($empresas_query);
        while ($empresa = $empresas_result->fetch_assoc()):
        ?>
            <option value="<?php echo $empresa['id_empresa']; ?>" 
                <?php echo (isset($user['id_empresa']) && $user['id_empresa'] == $empresa['id_empresa']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($empresa['nombre']); ?>
            </option>
        <?php endwhile; ?>
    </select>
    <small class="form-text text-muted">Dejar vacío para acceso a todas las empresas (solo admin)</small>
</div>
```

## Identificación Visual

### Agregar Badge de Empresa en Headers

Modificar `includes/header.php` o `includes/modern-header.php`:

```php
<div class="user-info">
    <span><?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
    <?php echo getEmpresaBadge(); ?>
</div>
```

## Consideraciones Importantes

1. **Usuario Admin**: Debe tener `id_empresa = NULL` para ver todas las empresas
2. **Migración de Datos**: Los tickets existentes se asignan automáticamente según el requester
3. **Configuración de Email**: Cada empresa puede tener su propia configuración SMTP/IMAP
4. **Seguridad**: Siempre validar que el usuario tenga acceso a la empresa antes de mostrar datos
5. **Performance**: Los índices en `id_empresa` mejoran el rendimiento de las consultas

## Troubleshooting

### Usuario no ve datos
- Verificar que el usuario tenga `id_empresa` asignada
- Verificar que los datos tengan `id_empresa` asignada
- Verificar que se esté aplicando el filtro correctamente

### Admin no ve todos los datos
- Verificar que `id_empresa = NULL` en la tabla usuarios
- Verificar que `getCurrentUserEmpresaId()` retorne `null` para admin

### Emails no se procesan correctamente
- Verificar configuración de email en `empresa_email_config`
- Verificar que `activo = 1` en la configuración
- Verificar logs de error de IMAP

## Próximos Pasos

1. Ejecutar script SQL de migración
2. Asignar empresas a usuarios existentes
3. Configurar email por empresa
4. Modificar archivos PHP según ejemplos
5. Probar funcionalidad con diferentes usuarios
6. Agregar badges de empresa en interfaces

