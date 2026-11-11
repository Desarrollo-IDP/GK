# Plan de Implementación por Fases: Sistema Multi-Empresa

## 📋 Índice

1. [Fase 0: Preparación](#fase-0-preparación)
2. [Fase 1: Base de Datos](#fase-1-base-de-datos)
3. [Fase 2: Sistema de Autenticación](#fase-2-sistema-de-autenticación)
4. [Fase 3: Gestión de Usuarios](#fase-3-gestión-de-usuarios)
5. [Fase 4: Configuración de Email](#fase-4-configuración-de-email)
6. [Fase 5: Módulo de Tickets](#fase-5-módulo-de-tickets)
7. [Fase 6: Módulo de Inventario](#fase-6-módulo-de-inventario)
8. [Fase 7: Módulo de Almacén](#fase-7-módulo-de-almacén)
9. [Fase 8: Interfaz y Visualización](#fase-8-interfaz-y-visualización)
10. [Fase 9: Pruebas y Validación](#fase-9-pruebas-y-validación)

---

## Fase 0: Preparación

### Objetivo
Preparar el entorno y entender la estructura del sistema antes de comenzar.

### Tareas

#### ✅ 0.1 Verificar Archivos Creados
- [x] `sql/multi_empresa_migration.sql` existe
- [x] `includes/empresa_helper.php` existe
- [x] `tickets/modules/configuration/empresa_email_config.php` existe
- [x] `tickets/conexion/get_empresa_email_config.php` existe

#### ✅ 0.2 Verificar Archivos Modificados
- [x] `auth.php` modificado
- [x] `index.php` modificado

#### 📝 0.3 Hacer Backup
```bash
# Backup de base de datos
mysqldump -u usuario -p nombre_base_datos > backup_antes_multi_empresa.sql

# Backup de archivos PHP principales
# Copiar carpeta completa del proyecto
```

#### 📝 0.4 Revisar Documentación
- [ ] Leer `GUIA_MULTI_EMPRESA.md`
- [ ] Revisar `EJEMPLOS_MODIFICACION.md`
- [ ] Entender funciones en `includes/empresa_helper.php`

### Tiempo Estimado: 30 minutos

---

## Fase 1: Base de Datos

### Objetivo
Migrar la estructura de base de datos para soportar multi-empresa.

### Tareas

#### ✅ 1.1 Ejecutar Script de Migración
```bash
mysql -u usuario -p nombre_base_datos < sql/multi_empresa_migration.sql
```

**Verificar:**
- [x] Script se ejecutó sin errores (Usuario confirmó)
- [x] Campos `id_empresa` agregados a todas las tablas
- [x] Tabla `empresa_email_config` creada
- [x] Índices creados correctamente

**✅ FASE 1 COMPLETADA**

#### 📝 1.2 Verificar Estructura
```sql
-- Verificar campos agregados
SHOW COLUMNS FROM usuarios LIKE 'id_empresa';
SHOW COLUMNS FROM tickets LIKE 'id_empresa';
SHOW COLUMNS FROM equipos LIKE 'id_empresa';
SHOW COLUMNS FROM bodegas LIKE 'id_empresa';

-- Verificar tabla nueva
DESCRIBE empresa_email_config;
```

#### 📝 1.3 Asignar Empresas a Usuarios Existentes
```sql
-- Listar usuarios existentes
SELECT id_usuario, nombre_usuario, rol FROM usuarios;

-- Asignar empresa a usuarios (ajustar según tus necesidades)
UPDATE usuarios SET id_empresa = 1 WHERE nombre_usuario = 'usuario1';
UPDATE usuarios SET id_empresa = 2 WHERE nombre_usuario = 'usuario2';

-- IMPORTANTE: Admin debe tener id_empresa = NULL
UPDATE usuarios SET id_empresa = NULL WHERE nombre_usuario = 'admin';
```

**Verificar:**
- [ ] Usuarios tienen empresa asignada
- [ ] Admin tiene `id_empresa = NULL`
- [ ] Al menos 2 usuarios de prueba con diferentes empresas

### Tiempo Estimado: 1 hora

---

## Fase 2: Sistema de Autenticación

### Objetivo
Verificar que el sistema de autenticación carga correctamente la empresa del usuario.

### Tareas

#### ✅ 2.1 Verificar Modificaciones en auth.php
- [x] Archivo incluye `empresa_helper.php`
- [x] Funciones helper disponibles

#### ✅ 2.2 Verificar Modificaciones en index.php
- [x] `compareUsernameInsensitive()` incluye empresa
- [x] Sesión incluye `id_empresa` y `empresa_nombre`

#### ✅ 2.3 Probar Login
**Script de Verificación Creado:** `verificar_fase2.php`

**Pasos para verificar:**
1. Iniciar sesión con un usuario normal (con empresa asignada)
2. Acceder a: `/verificar_fase2.php`
3. Verificar que muestra correctamente la información de empresa
4. Iniciar sesión con admin (sin empresa)
5. Verificar que muestra "Todas las empresas"

**Verificar:**
- [x] Login funciona correctamente
- [x] Sesión contiene `id_empresa`
- [x] Sesión contiene `empresa_nombre`
- [x] Admin tiene `id_empresa = null`
- [x] Funciones helper funcionan correctamente
- [x] Badge de empresa se muestra correctamente

**✅ FASE 2 COMPLETADA**

### Tiempo Estimado: 30 minutos

---

## Fase 3: Gestión de Usuarios

### Objetivo
Permitir asignar empresas a usuarios desde la interfaz de gestión.

### Tareas

#### 📝 3.1 Modificar Formulario de Creación
**Archivo:** `gestion-usuarios.php`

**Ubicación:** Buscar formulario de creación (alrededor de línea 267)

**Cambios:**
1. Agregar campo `id_empresa` en el INSERT:
```php
// ANTES (línea ~267)
$stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, rol, email, can_view_inventory_history, can_view_ticket_history, can_manage_users) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssiii", $nombre_usuario, $hashed_password, $rol, $email, $perm_hist_inventario, $perm_hist_tickets, $perm_manage_users);

// DESPUÉS
$id_empresa = !empty($_POST['id_empresa']) ? intval($_POST['id_empresa']) : null;
$stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, rol, email, id_empresa, can_view_inventory_history, can_view_ticket_history, can_manage_users) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssiiii", $nombre_usuario, $hashed_password, $rol, $email, $id_empresa, $perm_hist_inventario, $perm_hist_tickets, $perm_manage_users);
```

2. Agregar campo HTML en el formulario (buscar formulario de creación):
```php
<div class="form-group">
    <label for="id_empresa">Empresa</label>
    <select class="form-control" id="id_empresa" name="id_empresa">
        <option value="">Sin empresa (Admin - Ve todas las empresas)</option>
        <?php
        $empresas_query = "SELECT id_empresa, nombre FROM empresas ORDER BY nombre";
        $empresas_result = $conn->query($empresas_query);
        while ($empresa = $empresas_result->fetch_assoc()):
        ?>
            <option value="<?php echo $empresa['id_empresa']; ?>">
                <?php echo htmlspecialchars($empresa['nombre']); ?>
            </option>
        <?php endwhile; ?>
    </select>
    <small class="form-text text-muted">
        Dejar vacío para acceso a todas las empresas (solo para administradores)
    </small>
</div>
```

#### 📝 3.2 Modificar Formulario de Edición
**Ubicación:** Buscar sección de edición (alrededor de línea 411)

**Cambios:**
1. Agregar actualización de `id_empresa` (después de línea 426):
```php
if (isset($_POST['id_empresa'])) {
    $id_empresa = !empty($_POST['id_empresa']) ? intval($_POST['id_empresa']) : null;
    $updates[] = "id_empresa = ?";
    $types .= "i";
    $params[] = $id_empresa;
}
```

2. Agregar mismo campo HTML en formulario de edición (con selected):
```php
<div class="form-group">
    <label for="id_empresa">Empresa</label>
    <select class="form-control" id="id_empresa" name="id_empresa">
        <option value="">Sin empresa (Admin - Ve todas las empresas)</option>
        <?php
        $empresas_query = "SELECT id_empresa, nombre FROM empresas ORDER BY nombre";
        $empresas_result = $conn->query($empresas_query);
        while ($empresa = $empresas_result->fetch_assoc()):
        ?>
            <option value="<?php echo $empresa['id_empresa']; ?>" 
                <?php echo (isset($editUser['id_empresa']) && $editUser['id_empresa'] == $empresa['id_empresa']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($empresa['nombre']); ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>
```

#### 📝 3.3 Modificar Consulta de Listado
**Ubicación:** Línea ~486

**Cambios:**
```php
// ANTES
$query = "SELECT * FROM usuarios ORDER BY nombre_usuario";

// DESPUÉS
$query = "SELECT u.*, e.nombre as empresa_nombre 
          FROM usuarios u 
          LEFT JOIN empresas e ON u.id_empresa = e.id_empresa 
          ORDER BY nombre_usuario";
```

#### 📝 3.4 Agregar Columna de Empresa en Tabla
**Ubicación:** HTML de la tabla de usuarios

**Cambios:**
1. Agregar header:
```php
<th>Empresa</th>
```

2. Agregar celda en el cuerpo:
```php
<td>
    <?php 
    if ($user['id_empresa']): 
        echo htmlspecialchars($user['empresa_nombre'] ?? 'Sin empresa');
    else:
        echo '<span class="badge badge-info">Todas</span>';
    endif; 
    ?>
</td>
```

#### ✅ 3.5 Modificaciones Completadas
- [x] Campo `id_empresa` agregado en INSERT de creación
- [x] Campo `id_empresa` agregado en UPDATE de edición (con y sin contraseña)
- [x] Consulta de listado modificada con JOIN a empresas
- [x] Columna de empresa agregada en tabla HTML
- [x] Campo de empresa agregado en formulario de creación
- [x] Campo de empresa agregado en formulario de edición
- [x] JavaScript actualizado para cargar empresa en modal de edición

**Próximos pasos para probar:**
- [ ] Crear nuevo usuario con empresa asignada
- [ ] Editar usuario y cambiar empresa
- [ ] Verificar que se muestra empresa en listado
- [ ] Verificar que admin puede crear usuarios sin empresa

**✅ FASE 3 COMPLETADA (Implementación)**

### Tiempo Estimado: 1-2 horas

---

## Fase 4: Configuración de Email

### Objetivo
Configurar email (SMTP/IMAP) por empresa para procesamiento de tickets.

### Tareas

#### ✅ 4.1 Verificar Archivo de Configuración
- [x] `tickets/modules/configuration/empresa_email_config.php` existe
- [x] `tickets/conexion/get_empresa_email_config.php` existe

#### ✅ 4.2 Script de Verificación Creado
**Script de Verificación:** `verificar_fase4.php`

**Funciones verificadas:**
- [x] `getEmpresaEmailConfig()` - Obtiene configuración de email por empresa
- [x] `createMailerForEmpresa()` - Crea instancia PHPMailer para empresa
- [x] `getImapConfigForEmpresa()` - Obtiene configuración IMAP por empresa

#### 📝 4.3 Acceder a Interfaz de Configuración
1. Iniciar sesión como admin
2. Navegar a: `/tickets/modules/configuration/empresa_email_config.php`
3. Verificar que la página carga correctamente
4. O usar el script de verificación: `/verificar_fase4.php`

#### 📝 4.4 Configurar Email para Primera Empresa
1. Seleccionar empresa del dropdown
2. Llenar campos SMTP:
   - SMTP Host
   - SMTP Puerto (587)
   - SMTP Usuario
   - SMTP Contraseña
   - Email Remitente
   - Nombre Remitente
3. Llenar campos IMAP:
   - IMAP Host
   - IMAP Puerto (993)
   - IMAP Usuario
   - IMAP Contraseña
   - Carpeta IMAP (INBOX)
   - Cifrado (/imap/ssl)
4. Marcar como activo
5. Guardar

**Verificar:**
- [ ] Configuración se guarda correctamente
- [ ] Aparece en lista de configuraciones
- [ ] Se puede editar
- [ ] Se puede eliminar
- [ ] Funciones helper retornan configuración correcta

#### 📝 4.5 Configurar Email para Otras Empresas
- [ ] Repetir proceso para cada empresa que necesite email

**✅ FASE 4 COMPLETADA (Implementación - Lista para usar)**

### Tiempo Estimado: 1 hora

---

## Fase 5: Módulo de Tickets

### Objetivo
Implementar filtrado por empresa en el módulo de tickets.

### Tareas

#### 📝 5.1 Modificar tickets.php (Listado Principal)
**Archivo:** `tickets/modules/tickets/tickets.php`

**Cambios:**

1. **Agregar helper al inicio:**
```php
require_once '../../../includes/empresa_helper.php';
```

2. **Modificar consulta (alrededor de línea 30):**
```php
// ANTES
$query = "SELECT t.*, 
      c.name as category_name, 
      s.name as status_name,
      cm.name as contact_mode_name,
      r.name as requester_name,
      tech.name as technician_name
      FROM tickets t
      LEFT JOIN categories c ON t.category_id = c.id
      LEFT JOIN statuses s ON t.status_id = s.id
      LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
      LEFT JOIN requesters r ON t.requester_id = r.id
      LEFT JOIN technicians tech ON t.technician_id = tech.id
      WHERE t.closed_at IS NULL AND t.deleted != 1";

// DESPUÉS
$query = "SELECT t.*, 
      c.name as category_name, 
      s.name as status_name,
      cm.name as contact_mode_name,
      r.name as requester_name,
      tech.name as technician_name,
      e.nombre as empresa_nombre
      FROM tickets t
      LEFT JOIN categories c ON t.category_id = c.id
      LEFT JOIN statuses s ON t.status_id = s.id
      LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
      LEFT JOIN requesters r ON t.requester_id = r.id
      LEFT JOIN technicians tech ON t.technician_id = tech.id
      LEFT JOIN empresas e ON t.id_empresa = e.id_empresa
      WHERE t.closed_at IS NULL AND t.deleted != 1";

// Agregar filtro de empresa
$params = [];
$types = '';
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
```

3. **Modificar ejecución de consulta:**
```php
// ANTES
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();

// DESPUÉS (ya debería estar así, verificar)
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
```

4. **Agregar badge de empresa en tabla:**
```php
<td>
    <?php echo getEmpresaBadge($ticket['id_empresa'], $ticket['empresa_nombre']); ?>
</td>
```

#### 📝 5.2 Modificar new.php (Crear Ticket)
**Archivo:** `tickets/modules/tickets/new.php`

**Cambios:**

1. **Agregar helper al inicio:**
```php
require_once '../../../includes/empresa_helper.php';
```

2. **Al procesar formulario, asignar empresa:**
```php
$user_empresa_id = getCurrentUserEmpresaId();

// En el INSERT del ticket
$stmt = $conn->prepare("INSERT INTO tickets (subject, description, requester_id, technician_id, id_empresa, ...) VALUES (?, ?, ?, ?, ?, ...)");
$stmt->bind_param("ssiiii...", $subject, $description, $requester_id, $technician_id, $user_empresa_id, ...);
```

#### 📝 5.3 Modificar edit.php (Editar Ticket)
**Archivo:** `tickets/modules/tickets/edit.php`

**Cambios:**

1. **Agregar helper al inicio**
2. **Agregar filtro de empresa en consulta de ticket**
3. **Verificar que usuario puede editar solo tickets de su empresa**

#### 📝 5.4 Modificar view.php (Ver Ticket)
**Archivo:** `tickets/modules/tickets/view.php`

**Cambios:**

1. **Agregar helper al inicio**
2. **Agregar filtro de empresa**
3. **Mostrar badge de empresa**

#### 📝 5.5 Modificar all_tickets.php
**Archivo:** `tickets/modules/tickets/all_tickets.php`

**Cambios:**
- Similar a `tickets.php` pero incluyendo tickets cerrados

#### 📝 5.6 Modificar closed_tickets.php
**Archivo:** `tickets/modules/tickets/closed_tickets.php`

**Cambios:**
- Similar a `tickets.php` pero solo tickets cerrados

#### 📝 5.7 Modificar outlook_emails.php (CRÍTICO)
**Archivo:** `tickets/modules/outlook_emails.php`

**Cambios:**

1. **Agregar al inicio:**
```php
require_once(__DIR__ . '/../conexion/get_empresa_email_config.php');
```

2. **Modificar función principal para procesar por empresa:**
```php
// Obtener todas las empresas con configuración de email activa
$empresas_query = "SELECT eec.*, e.nombre as empresa_nombre 
                   FROM empresa_email_config eec
                   INNER JOIN empresas e ON eec.id_empresa = e.id_empresa
                   WHERE eec.activo = 1";
$empresas_result = $conn->query($empresas_query);

while ($empresa_config = $empresas_result->fetch_assoc()) {
    $empresa_id = $empresa_config['id_empresa'];
    
    // Obtener configuración IMAP para esta empresa
    $imap_config = getImapConfigForEmpresa($empresa_id, $conn);
    
    // Conectar a IMAP con configuración de la empresa
    $mailbox_string = "{" . $imap_config['host'] . ":" . $imap_config['port'] . $imap_config['encryption'] . $imap_config['options'] . "}" . $imap_config['folder'];
    $mailbox = @imap_open($mailbox_string, $imap_config['username'], $imap_config['password']);
    
    if (!$mailbox) {
        error_log("Error conectando a IMAP para empresa {$empresa_id}: " . imap_last_error());
        continue;
    }
    
    // Procesar emails
    $emails = imap_search($mailbox, 'UNSEEN');
    
    if ($emails) {
        foreach ($emails as $email_number) {
            // ... procesar email (código existente) ...
            
            // Al crear el ticket, asignar la empresa
            // Buscar donde se hace INSERT INTO tickets y agregar id_empresa
            $stmt = $conn->prepare("INSERT INTO tickets (subject, description, requester_id, id_empresa, ...) VALUES (?, ?, ?, ?, ...)");
            $stmt->bind_param("ssii...", $subject, $description, $requester_id, $empresa_id, ...);
            $stmt->execute();
        }
    }
    
    imap_close($mailbox);
}
```

#### 📝 5.8 Modificar Módulos Relacionados
- [ ] `tickets/modules/requester/requester.php` - Agregar filtro
- [ ] `tickets/modules/requester/add.php` - Asignar empresa
- [ ] `tickets/modules/technicians/technicians.php` - Agregar filtro
- [ ] `tickets/modules/technicians/add.php` - Asignar empresa
- [ ] `tickets/modules/reports/reports.php` - Agregar filtro

#### 📝 5.9 Probar Funcionalidad
- [ ] Crear ticket como usuario de empresa A
- [ ] Verificar que solo usuarios de empresa A lo ven
- [ ] Verificar que admin ve todos los tickets
- [ ] Probar procesamiento de emails por empresa

### Tiempo Estimado: 3-4 horas

---

## Fase 6: Módulo de Inventario

### Objetivo
Implementar filtrado por empresa en el módulo de inventario.

### Tareas

#### 📝 6.1 Modificar visualizar-equipos.php
**Archivo:** `Inventario/crud/visualizar-equipos.php`

**Cambios:**

1. **Agregar helper al inicio:**
```php
require_once '../../includes/empresa_helper.php';
```

2. **Modificar consulta:**
```php
// La consulta ya tiene JOIN con empresas, solo agregar filtro
// Buscar donde se construye $query y agregar:

$params = [];
$types = '';

// Si ya hay condiciones WHERE
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
    $query = addEmpresaFilter($query, 'e.id_empresa', $params, $types);
} else {
    $query = addEmpresaFilter($query, 'e.id_empresa', $params, $types);
}

// Modificar ejecución
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
```

#### 📝 6.2 Modificar agregar-equipo.php
**Archivo:** `Inventario/crud/agregar-equipo.php`

**Cambios:**

1. **Agregar helper al inicio**
2. **Al crear equipo, asignar empresa:**
```php
$user_empresa_id = getCurrentUserEmpresaId();

// En el INSERT
$stmt = $conn->prepare("INSERT INTO equipos (nombre_cliente, nombre_equipo, responsable, id_empresa, ...) VALUES (?, ?, ?, ?, ...)");
$stmt->bind_param("sssi...", $nombre_cliente, $nombre_equipo, $responsable, $user_empresa_id, ...);
```

#### 📝 6.3 Modificar editar-equipo.php
**Archivo:** `Inventario/crud/editar-equipo.php`

**Cambios:**
- Agregar filtro de empresa al obtener equipo
- Verificar que usuario puede editar solo equipos de su empresa

#### 📝 6.4 Modificar visualizar-empresas.php
**Archivo:** `Inventario/crud/visualizar-empresas.php`

**Cambios:**
- Agregar filtro si es necesario (admin ve todas, usuarios solo su empresa)

#### 📝 6.5 Modificar historial-actividades.php
**Archivo:** `Inventario/crud/historial-actividades.php`

**Cambios:**
- Agregar filtro por empresa en consulta de historial

#### 📝 6.6 Modificar inicio.php
**Archivo:** `Inventario/crud/inicio.php`

**Cambios:**
- Agregar filtro en consulta de estadísticas

#### 📝 6.7 Probar Funcionalidad
- [ ] Crear equipo como usuario de empresa A
- [ ] Verificar que solo usuarios de empresa A lo ven
- [ ] Verificar que admin ve todos los equipos
- [ ] Probar búsqueda y filtros

### Tiempo Estimado: 2-3 horas

---

## Fase 7: Módulo de Almacén

### Objetivo
Implementar filtrado por empresa en el módulo de almacén.

### Tareas

#### 📝 7.1 Modificar bodegas.php
**Archivo:** `almacen/modules/bodegas/bodegas.php`

**Cambios:**

1. **Agregar helper al inicio:**
```php
require_once '../../../includes/empresa_helper.php';
```

2. **Agregar filtro en consultas:**
```php
$query = "SELECT * FROM bodegas WHERE estado_bodega = 'Activa'";
$params = [];
$types = '';
$query = addEmpresaFilter($query, 'id_empresa', $params, $types);
```

3. **Al crear bodega, asignar empresa:**
```php
$user_empresa_id = getCurrentUserEmpresaId();
```

#### 📝 7.2 Modificar almacenes.php
**Archivo:** `almacen/modules/almacenes/almacenes.php`

**Cambios:**
- Similar a bodegas.php

#### 📝 7.3 Modificar productos.php
**Archivo:** `almacen/modules/productos/productos.php`

**Cambios:**
- Similar a bodegas.php

#### 📝 7.4 Modificar cartas-entrega.php
**Archivo:** `almacen/modules/cartas/cartas-entrega.php`

**Cambios:**
- Agregar filtro y asignar empresa al crear

#### 📝 7.5 Modificar historial.php
**Archivo:** `almacen/modules/historial/historial.php`

**Cambios:**
- Agregar filtro por empresa

#### 📝 7.6 Modificar inicio.php
**Archivo:** `almacen/modules/inicio/inicio.php`

**Cambios:**
- Agregar filtros en estadísticas

#### 📝 7.7 Probar Funcionalidad
- [ ] Crear bodega como usuario de empresa A
- [ ] Verificar que solo usuarios de empresa A la ven
- [ ] Verificar que admin ve todas las bodegas
- [ ] Probar con productos y cartas

### Tiempo Estimado: 2-3 horas

---

## Fase 8: Interfaz y Visualización

### Objetivo
Agregar badges de empresa en las interfaces para identificación visual.

### Tareas

#### 📝 8.1 Agregar Badge en Header de Tickets
**Archivo:** `tickets/includes/modern-header.php` o `tickets/includes/header.php`

**Cambios:**
```php
<?php
if (file_exists(__DIR__ . '/../../../includes/empresa_helper.php')) {
    require_once __DIR__ . '/../../../includes/empresa_helper.php';
    echo getEmpresaBadge();
}
?>
```

#### 📝 8.2 Agregar Badge en Header de Inventario
**Archivo:** `Inventario/crud/includes/header.php`

**Cambios:**
```php
<?php
if (file_exists(__DIR__ . '/../../../includes/empresa_helper.php')) {
    require_once __DIR__ . '/../../../includes/empresa_helper.php';
    echo getEmpresaBadge();
}
?>
```

#### 📝 8.3 Agregar Badge en Header de Almacén
**Archivo:** `almacen/includes/header.php`

**Cambios:**
```php
<?php
if (file_exists(__DIR__ . '/../../includes/empresa_helper.php')) {
    require_once __DIR__ . '/../../includes/empresa_helper.php';
    echo getEmpresaBadge();
}
?>
```

#### 📝 8.4 Agregar Badge en direc.php
**Archivo:** `direc.php`

**Cambios:**
- Agregar badge cerca del nombre de usuario

#### 📝 8.5 Verificar Visualización
- [ ] Badge aparece en todas las páginas
- [ ] Muestra nombre de empresa correcto
- [ ] Admin muestra "Todas las empresas"
- [ ] Diseño es consistente

### Tiempo Estimado: 1 hora

---

## Fase 9: Pruebas y Validación

### Objetivo
Verificar que todo funciona correctamente con diferentes usuarios y empresas.

### Tareas

#### 📝 9.1 Preparar Usuarios de Prueba
```sql
-- Crear usuarios de prueba para diferentes empresas
-- Usuario 1: Empresa A
-- Usuario 2: Empresa B
-- Admin: Sin empresa
```

#### 📝 9.2 Pruebas de Tickets
- [ ] Usuario A crea ticket → Solo usuario A y admin lo ven
- [ ] Usuario B crea ticket → Solo usuario B y admin lo ven
- [ ] Admin crea ticket → Todos lo ven
- [ ] Procesamiento de emails crea tickets con empresa correcta
- [ ] Filtros funcionan correctamente

#### 📝 9.3 Pruebas de Inventario
- [ ] Usuario A crea equipo → Solo usuario A y admin lo ven
- [ ] Usuario B crea equipo → Solo usuario B y admin lo ven
- [ ] Búsqueda funciona correctamente
- [ ] Filtros por fecha funcionan con empresa

#### 📝 9.4 Pruebas de Almacén
- [ ] Usuario A crea bodega → Solo usuario A y admin la ven
- [ ] Usuario A crea producto → Solo usuario A y admin lo ven
- [ ] Cartas de entrega se filtran correctamente

#### 📝 9.5 Pruebas de Configuración de Email
- [ ] Cada empresa puede tener su propia configuración
- [ ] Emails se procesan con configuración correcta
- [ ] Tickets creados desde email tienen empresa correcta

#### 📝 9.6 Pruebas de Seguridad
- [ ] Usuario no puede ver datos de otra empresa
- [ ] Usuario no puede editar datos de otra empresa
- [ ] Admin puede ver y editar todo
- [ ] URLs directas no permiten acceso no autorizado

#### 📝 9.7 Pruebas de Rendimiento
- [ ] Consultas con filtros son rápidas
- [ ] Índices funcionan correctamente
- [ ] No hay consultas N+1

### Tiempo Estimado: 2-3 horas

---

## Resumen de Tiempos

| Fase | Tiempo Estimado |
|------|----------------|
| Fase 0: Preparación | 30 min |
| Fase 1: Base de Datos | 1 hora |
| Fase 2: Autenticación | 30 min |
| Fase 3: Gestión de Usuarios | 1-2 horas |
| Fase 4: Configuración de Email | 1 hora |
| Fase 5: Módulo de Tickets | 3-4 horas |
| Fase 6: Módulo de Inventario | 2-3 horas |
| Fase 7: Módulo de Almacén | 2-3 horas |
| Fase 8: Interfaz y Visualización | 1 hora |
| Fase 9: Pruebas y Validación | 2-3 horas |
| **TOTAL** | **14-19 horas** |

---

## Checklist General de Implementación

### Antes de Comenzar
- [ ] Backup completo de base de datos
- [ ] Backup de archivos PHP
- [ ] Entorno de desarrollo configurado
- [ ] Documentación leída

### Durante la Implementación
- [ ] Seguir fases en orden
- [ ] Probar cada fase antes de continuar
- [ ] Documentar problemas encontrados
- [ ] Hacer commits frecuentes

### Después de Implementar
- [ ] Todas las pruebas pasan
- [ ] Documentación actualizada
- [ ] Usuarios entrenados
- [ ] Sistema en producción

---

## Notas Importantes

1. **No saltar fases**: Cada fase depende de la anterior
2. **Probar frecuentemente**: No esperar hasta el final para probar
3. **Backup constante**: Hacer backup antes de cada fase importante
4. **Documentar cambios**: Anotar cualquier modificación adicional
5. **Consultar ejemplos**: Revisar `EJEMPLOS_MODIFICACION.md` cuando tengas dudas

---

## Soporte y Recursos

- **Guía completa**: `GUIA_MULTI_EMPRESA.md`
- **Ejemplos de código**: `EJEMPLOS_MODIFICACION.md`
- **Funciones helper**: `includes/empresa_helper.php`
- **Plan general**: `IMPLEMENTACION_MULTI_EMPRESA.md`

---

**Última actualización:** 2025-01-27
**Versión del plan:** 1.0

