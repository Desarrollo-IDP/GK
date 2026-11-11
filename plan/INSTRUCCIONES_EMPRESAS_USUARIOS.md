# Instrucciones: Gestión de Empresas de Usuarios

## 📋 Resumen

Se ha creado una nueva tabla `empresas_usuarios` para gestionar empresas de usuarios de forma independiente de la tabla `empresas` que se usa para inventario.

## 🗄️ Script SQL

### Ejecutar el Script

```bash
# Desde línea de comandos MySQL
mysql -u usuario -p nombre_base_datos < sql/create_empresas_usuarios_table.sql

# O desde phpMyAdmin:
# 1. Seleccionar tu base de datos
# 2. Ir a la pestaña "SQL"
# 3. Copiar y pegar el contenido de sql/create_empresas_usuarios_table.sql
# 4. Ejecutar
```

### Estructura de la Tabla

La tabla `empresas_usuarios` tiene los siguientes campos:

- `id_empresa` (INT, AUTO_INCREMENT, PRIMARY KEY)
- `nombre` (VARCHAR(255), NOT NULL)
- `descripcion` (TEXT, NULL)
- `direccion` (VARCHAR(500), NULL)
- `telefono` (VARCHAR(50), NULL)
- `email` (VARCHAR(255), NULL)
- `contacto` (VARCHAR(255), NULL)
- `activo` (TINYINT(1), DEFAULT 1)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

## 🔧 Cambios Realizados

### 1. Archivo `gestion-usuarios.php`

**Cambios realizados:**

1. ✅ Todas las consultas ahora usan `empresas_usuarios` en lugar de `empresas`
2. ✅ Agregado botón "Gestionar Empresas" en la interfaz
3. ✅ Agregada sección completa de gestión de empresas con:
   - Listado de empresas
   - Formulario para agregar empresa
   - Formulario para editar empresa
   - Función para eliminar empresa (con validación)
4. ✅ Agregadas funciones JavaScript:
   - `openEmpresasSection()` - Muestra sección de empresas
   - `closeEmpresasSection()` - Oculta sección de empresas
   - `openAddEmpresaModal()` - Abre modal para agregar
   - `openEditEmpresaModal()` - Abre modal para editar
   - `eliminarEmpresa()` - Elimina empresa con confirmación

### 2. Acciones CRUD Agregadas

- `add_empresa` - Agregar nueva empresa
- `edit_empresa` - Editar empresa existente
- `delete_empresa` - Eliminar empresa (con validación de usuarios asociados)

## 📝 Uso

### Acceder a Gestión de Empresas

1. Iniciar sesión como administrador o usuario con permisos de gestión
2. Ir a `gestion-usuarios.php`
3. Click en el botón **"Gestionar Empresas"** (botón verde)
4. Se mostrará la sección de empresas debajo de la lista de usuarios

### Agregar Nueva Empresa

1. Click en **"Agregar Empresa"**
2. Llenar el formulario:
   - **Nombre** (obligatorio)
   - Descripción (opcional)
   - Dirección (opcional)
   - Teléfono (opcional)
   - Email (opcional)
   - Contacto (opcional)
   - Activa (checkbox, por defecto marcado)
3. Click en **"Agregar"**

### Editar Empresa

1. En la lista de empresas, click en **"Editar"** (icono de lápiz)
2. Modificar los campos necesarios
3. Click en **"Actualizar"**

### Eliminar Empresa

1. En la lista de empresas, click en **"Eliminar"** (icono de basura)
2. Confirmar la eliminación
3. **Nota:** No se puede eliminar si tiene usuarios asociados

## ⚠️ Validaciones

- **Al eliminar:** Se verifica que no haya usuarios asociados a la empresa
- **Al crear/editar:** El nombre es obligatorio
- **Estado activo:** Las empresas inactivas no aparecen en los dropdowns de selección

## 🔄 Migración de Datos (Si es necesario)

Si ya tienes usuarios con `id_empresa` apuntando a la tabla `empresas` (de inventario), necesitarás migrar esos datos:

```sql
-- 1. Verificar usuarios con empresa asignada
SELECT u.id_usuario, u.nombre_usuario, u.id_empresa, e.nombre as empresa_nombre
FROM usuarios u
LEFT JOIN empresas e ON u.id_empresa = e.id_empresa
WHERE u.id_empresa IS NOT NULL;

-- 2. Crear empresas en empresas_usuarios basadas en empresas existentes (opcional)
INSERT INTO empresas_usuarios (nombre, descripcion, direccion, activo)
SELECT nombre, direccion, direccion, 1
FROM empresas
WHERE id_empresa IN (SELECT DISTINCT id_empresa FROM usuarios WHERE id_empresa IS NOT NULL);

-- 3. Actualizar usuarios para usar empresas_usuarios (ajustar IDs según corresponda)
-- NOTA: Esto requiere mapear los IDs manualmente o crear un script de migración
```

## 📌 Notas Importantes

1. **Tabla independiente:** `empresas_usuarios` es completamente independiente de `empresas` (inventario)
2. **Dropdowns:** Los dropdowns de selección de empresa solo muestran empresas activas
3. **Validación:** No se puede eliminar una empresa si tiene usuarios asociados
4. **Compatibilidad:** El código sigue funcionando con usuarios sin empresa asignada (NULL)

## ✅ Verificación

Después de ejecutar el script SQL:

```sql
-- Verificar que la tabla existe
SHOW TABLES LIKE 'empresas_usuarios';

-- Ver estructura
DESCRIBE empresas_usuarios;

-- Ver datos (debería estar vacía inicialmente)
SELECT * FROM empresas_usuarios;
```

## 🎯 Próximos Pasos

1. ✅ Ejecutar script SQL para crear la tabla
2. ✅ Probar agregar una empresa desde la interfaz
3. ✅ Probar editar una empresa
4. ✅ Asignar empresa a usuarios
5. ✅ Verificar que los usuarios ven solo datos de su empresa

---

**Fecha de creación:** 2025-01-27
**Archivos modificados:**
- `sql/create_empresas_usuarios_table.sql` (nuevo)
- `gestion-usuarios.php` (modificado)

