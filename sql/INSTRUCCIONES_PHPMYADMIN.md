# Instrucciones para Ejecutar Migración en phpMyAdmin

## Problema Común

phpMyAdmin puede tener problemas con comandos ALTER TABLE que incluyen múltiples operaciones en una sola línea. Este documento explica cómo ejecutar la migración correctamente.

## Solución: Usar Script Separado

He creado dos versiones del script:

1. **`multi_empresa_migration_phpmanual.sql`** - Versión con comandos separados (RECOMENDADO)
2. **`multi_empresa_migration.sql`** - Versión original (ya corregida)

## Método 1: Ejecutar Script Completo (Recomendado)

### Paso 1: Abrir phpMyAdmin
1. Acceder a phpMyAdmin
2. Seleccionar tu base de datos

### Paso 2: Ir a la pestaña SQL
1. Click en la pestaña "SQL" en la parte superior
2. Click en "Importar archivo" o pegar el contenido del script

### Paso 3: Ejecutar Script
1. Abrir el archivo `sql/multi_empresa_migration_phpmanual.sql`
2. Copiar TODO el contenido
3. Pegar en el área de texto de phpMyAdmin
4. Click en "Continuar" o "Ejecutar"

**Si hay errores:**
- Si dice "columna ya existe", puedes continuar (es normal si ya ejecutaste parte del script)
- Si dice "tabla no existe", esa tabla no está en tu base de datos, puedes saltarla

## Método 2: Ejecutar Comandos Individualmente

Si el método 1 no funciona, ejecuta cada comando por separado:

### Paso 1: Verificar Estructura de Tablas

Antes de ejecutar, verifica qué tablas tienes:

```sql
SHOW TABLES;
```

### Paso 2: Ejecutar Comandos por Sección

Copia y pega cada comando individualmente:

#### Sección 1: Usuarios
```sql
ALTER TABLE `usuarios` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;
ALTER TABLE `usuarios` ADD INDEX `idx_usuarios_empresa` (`id_empresa`);
```

#### Sección 2: Tickets
```sql
ALTER TABLE `tickets` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `last_wa_message_id`;
ALTER TABLE `tickets` ADD INDEX `idx_tickets_empresa` (`id_empresa`);
```

#### Sección 3: Requesters
```sql
ALTER TABLE `requesters` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;
ALTER TABLE `requesters` ADD INDEX `idx_requesters_empresa` (`id_empresa`);
```

#### Sección 4: Technicians
```sql
ALTER TABLE `technicians` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;
ALTER TABLE `technicians` ADD INDEX `idx_technicians_empresa` (`id_empresa`);
```

#### Sección 5: Equipos
```sql
ALTER TABLE `equipos` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_equipo`;
ALTER TABLE `equipos` ADD INDEX `idx_equipos_empresa` (`id_empresa`);
```

#### Sección 6: Bodegas
```sql
ALTER TABLE `bodegas` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;
ALTER TABLE `bodegas` ADD INDEX `idx_bodegas_empresa` (`id_empresa`);
```

#### Sección 7: Almacenes
```sql
ALTER TABLE `almacenes` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_almacen`;
ALTER TABLE `almacenes` ADD INDEX `idx_almacenes_empresa` (`id_empresa`);
```

#### Sección 8: Productos Almacén
```sql
ALTER TABLE `productos_almacen` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_producto`;
ALTER TABLE `productos_almacen` ADD INDEX `idx_productos_empresa` (`id_empresa`);
```

#### Sección 9: Cartas Entrega
```sql
ALTER TABLE `cartas_entrega` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_carta`;
ALTER TABLE `cartas_entrega` ADD INDEX `idx_cartas_empresa` (`id_empresa`);
```

#### Sección 10: Historial Movimientos
```sql
ALTER TABLE `historial_movimientos` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_movimiento`;
ALTER TABLE `historial_movimientos` ADD INDEX `idx_historial_empresa` (`id_empresa`);
```

#### Sección 11: Historial Actividades
```sql
ALTER TABLE `historial_actividades` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id`;
ALTER TABLE `historial_actividades` ADD INDEX `idx_historial_actividades_empresa` (`id_empresa`);
```

#### Sección 12: Crear Tabla Email Config
```sql
CREATE TABLE IF NOT EXISTS `empresa_email_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` INT(11) NOT NULL,
  `smtp_host` VARCHAR(255) NOT NULL,
  `smtp_port` INT(11) NOT NULL DEFAULT 587,
  `smtp_username` VARCHAR(255) NOT NULL,
  `smtp_password` VARCHAR(255) NOT NULL,
  `smtp_from_email` VARCHAR(255) NOT NULL,
  `smtp_from_name` VARCHAR(255) NOT NULL DEFAULT 'Sistema de Tickets',
  `imap_host` VARCHAR(255) NOT NULL,
  `imap_port` INT(11) NOT NULL DEFAULT 993,
  `imap_username` VARCHAR(255) NOT NULL,
  `imap_password` VARCHAR(255) NOT NULL,
  `imap_folder` VARCHAR(100) NOT NULL DEFAULT 'INBOX',
  `imap_encryption` VARCHAR(50) NOT NULL DEFAULT '/imap/ssl',
  `imap_options` VARCHAR(255) NOT NULL DEFAULT '/imap/ssl/validate-cert',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_email` (`id_empresa`),
  INDEX `idx_empresa_email_activo` (`id_empresa`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Sección 13: Actualizar Tickets Existentes
```sql
UPDATE `tickets` t
INNER JOIN `requesters` r ON t.requester_id = r.id
SET t.id_empresa = r.id_empresa
WHERE t.id_empresa IS NULL AND r.id_empresa IS NOT NULL;
```

#### Sección 14: Crear Vista (Opcional)
```sql
CREATE OR REPLACE VIEW `v_usuarios_empresa` AS
SELECT 
    u.id_usuario,
    u.nombre_usuario,
    u.rol,
    u.id_empresa,
    e.nombre as empresa_nombre,
    u.email,
    u.can_view_inventory_history,
    u.can_view_ticket_history,
    u.can_manage_users
FROM usuarios u
LEFT JOIN empresas e ON u.id_empresa = e.id_empresa;
```

## Verificación

Después de ejecutar el script, verifica que todo esté correcto:

```sql
-- Verificar columnas agregadas
SHOW COLUMNS FROM usuarios LIKE 'id_empresa';
SHOW COLUMNS FROM tickets LIKE 'id_empresa';
SHOW COLUMNS FROM equipos LIKE 'id_empresa';
SHOW COLUMNS FROM bodegas LIKE 'id_empresa';
SHOW COLUMNS FROM historial_actividades LIKE 'id_empresa';

-- Verificar tabla nueva
DESCRIBE empresa_email_config;
```

## Errores Comunes y Soluciones

### Error: "Duplicate column name 'id_empresa'"
**Solución:** La columna ya existe, puedes continuar con el siguiente comando.

### Error: "Table doesn't exist"
**Solución:** Esa tabla no está en tu base de datos. Puedes saltarla si no la necesitas.

### Error: "Unknown column 'id_actividad'"
**Solución:** Ya corregido en el script. La tabla `historial_actividades` usa `id` como primera columna.

### Error: "Unknown column 'last_wa_message_id'"
**Solución:** Si la tabla `tickets` no tiene esa columna, usa:
```sql
ALTER TABLE `tickets` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL FIRST;
```

## Después de la Migración

1. **Asignar empresas a usuarios:**
```sql
UPDATE usuarios SET id_empresa = 1 WHERE nombre_usuario = 'usuario1';
UPDATE usuarios SET id_empresa = NULL WHERE nombre_usuario = 'admin';
```

2. **Verificar estructura:**
```sql
SELECT u.nombre_usuario, u.id_empresa, e.nombre as empresa_nombre 
FROM usuarios u 
LEFT JOIN empresas e ON u.id_empresa = e.id_empresa;
```

## Soporte

Si tienes problemas:
1. Verifica qué tablas existen en tu base de datos
2. Ejecuta comandos individualmente
3. Revisa los mensajes de error específicos
4. Consulta la documentación en `GUIA_MULTI_EMPRESA.md`

