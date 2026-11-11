# Instrucciones para el Módulo de Configuración

## 📋 Descripción

Se ha creado un nuevo módulo de **Configuración** que permite gestionar dinámicamente los catálogos del sistema. Este módulo permite agregar, editar y eliminar valores para los campos configurables de:

- **Bodegas**: Estado de la bodega
- **Almacenes**: Tipo de almacén, Estado del almacén
- **Productos**: Categoría, Estado del producto

## 🔧 Instalación

### Paso 1: Ejecutar el Script SQL

Ejecuta el archivo SQL para crear la tabla de catálogos e insertar los valores iniciales:

```sql
-- Ubicación: almacen/db/catalogos.sql
```

**Opciones para ejecutar:**

1. **Desde phpMyAdmin:**
   - Abre phpMyAdmin
   - Selecciona tu base de datos
   - Ve a la pestaña "SQL"
   - Copia y pega el contenido del archivo `catalogos.sql`
   - Haz clic en "Ejecutar"

2. **Desde la línea de comandos:**
   ```bash
   mysql -u tu_usuario -p tu_base_de_datos < almacen/db/catalogos.sql
   ```

### Paso 2: Verificar la Instalación

Los siguientes archivos deben estar en su lugar:

- ✅ `almacen/modules/configuracion.php` - Módulo principal
- ✅ `almacen/includes/get_catalogos.php` - API para cargar datos
- ✅ `almacen/db/catalogos.sql` - Script de base de datos
- ✅ `almacen/includes/header.php` - Actualizado con el nuevo menú

## 🎯 Características

### Funcionalidades Disponibles

1. **Vista de Tarjetas**
   - Visualización por módulo (Bodegas, Almacenes, Productos)
   - Contador de opciones por campo
   - Diseño responsive

2. **Gestión de Catálogos**
   - ✅ Agregar nuevos valores
   - ✅ Editar valores existentes
   - ✅ Eliminar valores
   - ✅ Activar/Desactivar valores
   - ✅ Ordenar valores

3. **Validaciones**
   - No permite duplicados
   - Campos obligatorios
   - Confirmación antes de eliminar

## 🚀 Uso

### Acceso al Módulo

1. Inicia sesión en el sistema
2. En el menú lateral, haz clic en **"Configuración"** (ícono de engranaje)
3. Verás 3 tarjetas: Bodegas, Almacenes y Productos

### Gestionar un Catálogo

1. **Haz clic en la tarjeta** del módulo que deseas configurar
2. Se abrirá un modal mostrando todos los campos configurables
3. Para cada campo puedes:
   - **Agregar**: Click en el botón "Agregar"
   - **Editar**: Click en el ícono de lápiz
   - **Eliminar**: Click en el ícono de basura

### Agregar un Nuevo Valor

1. Click en el botón **"Agregar"** del campo deseado
2. Completa el formulario:
   - **Valor**: El texto que aparecerá en los selects (obligatorio)
   - **Orden**: Número para ordenar la lista (opcional, default 0)
3. Click en **"Guardar"**

### Editar un Valor

1. Click en el ícono de **lápiz** del valor que deseas editar
2. Modifica los campos:
   - **Valor**: Cambia el texto
   - **Orden**: Cambia el orden
   - **Estado**: Activo/Inactivo
3. Click en **"Guardar"**

### Eliminar un Valor

1. Click en el ícono de **basura** del valor que deseas eliminar
2. Confirma la acción en el mensaje de alerta
3. El valor será eliminado permanentemente

## 📊 Estructura de Datos

### Tabla: `catalogos`

```sql
- id_catalogo (INT) - ID único
- modulo (VARCHAR) - Módulo: bodega, almacen, productos
- campo (VARCHAR) - Campo al que pertenece
- valor (VARCHAR) - Valor del catálogo
- activo (TINYINT) - 1=Activo, 0=Inactivo
- orden (INT) - Orden de visualización
- fecha_creacion (TIMESTAMP)
- fecha_actualizacion (TIMESTAMP)
```

### Catálogos Configurables

**Módulo: bodega**
- `estado_bodega`: Activa, Inactiva, Mantenimiento

**Módulo: almacen**
- `tipo_almacen`: General, Refrigerado, Congelado, Peligroso, Seguro
- `estado_almacen`: Disponible, Ocupado, Mantenimiento, Reservado

**Módulo: productos**
- `categoria`: Electrónica, Herramientas, Materia Prima, Consumibles
- `estado_producto`: Disponible, Reservado, Entregado, Dañado

## 🎨 Personalización

### Agregar un Nuevo Módulo

Para agregar un nuevo módulo al sistema de configuración:

1. **Actualiza el archivo `configuracion.php`:**

```php
$catalogos_config = [
    // ... módulos existentes ...
    [
        'modulo' => 'nuevo_modulo',
        'titulo' => 'Nuevo Módulo',
        'icono' => 'fa-icon-name',
        'color' => 'from-color-500 to-color-700',
        'campos' => [
            ['campo' => 'nombre_campo', 'nombre' => 'Nombre del Campo']
        ]
    ]
];
```

2. **Inserta valores iniciales en la BD:**

```sql
INSERT INTO catalogos (modulo, campo, valor, orden) VALUES
('nuevo_modulo', 'nombre_campo', 'Valor 1', 1),
('nuevo_modulo', 'nombre_campo', 'Valor 2', 2);
```

## 🔒 Seguridad

- ✅ Requiere autenticación (`checkAuth()`)
- ✅ Validación de datos de entrada
- ✅ Prepared statements para prevenir SQL injection
- ✅ Restricción de duplicados en la base de datos

## 🐛 Solución de Problemas

### Error: "Tabla catalogos no existe"
- **Solución**: Ejecuta el archivo `catalogos.sql`

### Error: "No se pueden cargar los catálogos"
- **Solución**: Verifica que el archivo `get_catalogos.php` existe y tiene permisos de lectura

### Los cambios no se reflejan en los formularios
- **Solución**: Los formularios actuales usan valores hardcodeados. Necesitas actualizar cada formulario para que cargue desde la tabla `catalogos`

## 📝 Notas Importantes

1. Los valores iniciales están precargados en el SQL
2. Los valores inactivos no aparecerán en los selects de los formularios
3. El orden de los valores se respeta en la visualización
4. No se puede eliminar un valor si está siendo usado en registros existentes

## 🔄 Próximos Pasos

Para que los formularios de Bodegas, Almacenes y Productos utilicen estos catálogos dinámicamente, debes actualizar los `<select>` en cada formulario para cargar desde la tabla `catalogos` en lugar de tener los valores hardcodeados.

## ✅ Verificación Final

Después de la instalación, verifica que:

- [ ] El menú "Configuración" aparece en el sidebar
- [ ] Al hacer clic se abre la página con 3 tarjetas
- [ ] Al hacer clic en una tarjeta se abre el modal de gestión
- [ ] Puedes agregar, editar y eliminar valores
- [ ] Los cambios se reflejan inmediatamente

## 📞 Soporte

Si encuentras algún problema, revisa:
1. Los logs de PHP (`error_log`)
2. La consola del navegador (F12)
3. Los permisos de archivos y carpetas

