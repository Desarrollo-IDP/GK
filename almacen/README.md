# 📦 Sistema de Gestión de Almacén

Sistema completo de gestión de almacén desarrollado para SecuríTI que permite administrar bodegas, almacenes, productos e inventario, además de generar cartas de entrega profesionales en PDF.

## 🎯 Características Principales

### ✅ Gestión de Bodegas
- CRUD completo de bodegas
- Gestión de ubicación, capacidad y estado
- Asignación de responsables
- Control de almacenes por bodega

### ✅ Gestión de Almacenes
- CRUD completo de almacenes
- Tipos de almacén (General, Refrigerado, Congelado, Peligroso, Seguro)
- Control de capacidad y ocupación
- Ubicación específica (Pasillo, Sección, Nivel)
- Control de temperatura y humedad

### ✅ Gestión de Productos
- Registro completo de productos
- Control de stock (mínimo, máximo, actual)
- Categorización y clasificación
- Ubicación exacta dentro del almacén
- Control de lotes y fechas de vencimiento
- Gestión de proveedores
- Alertas de stock bajo

### ✅ Cartas de Entrega
- Generación de cartas de entrega en PDF
- Basado en el sistema existente de Inventario
- Numeración automática (CA-0001, CA-0002, etc.)
- Tipos de entrega: Definitiva, Temporal, Préstamo
- Seguimiento de estados
- Firmas digitales

### ✅ Historial y Reportes
- Historial completo de movimientos
- Tipos de movimiento: Entrada, Salida, Transferencia, Ajuste, Entrega, Devolución
- Reportes estadísticos
- Dashboard con métricas en tiempo real
- Filtros avanzados

### ✅ Diseño Responsivo
- Implementado según especificaciones de `estilo.md`
- Breakpoints optimizados:
  - 📱 Mobile (320px - 768px)
  - 📟 Tablet (769px - 1024px)
  - 💻 Desktop (1025px - 1440px)
  - 🖥️ Large Screens (1441px+)
- Sidebar responsivo con menú hamburguesa
- Tablas adaptativas

## 🎨 Paleta de Colores

- **#000000** - Negro (botones principales)
- **#1E2A3B** - Azul oscuro (navbar, headers)
- **#414D5C** - Gris azulado (elementos secundarios)
- **#D1D5DB** - Gris claro (bordes, fondos)
- **#FFFFFF** - Blanco (fondos principales)

## 📁 Estructura del Proyecto

```
almacen/
├── css/
│   └── responsive.css          # Estilos responsivos
├── db/
│   └── almacen.sql            # Base de datos
├── includes/
│   ├── db.php                 # Conexión a BD
│   ├── header.php             # Header del sistema
│   └── footer.php             # Footer del sistema
├── js/
│   └── main.js                # JavaScript principal
├── modules/
│   ├── inicio.php             # Dashboard
│   ├── bodegas.php            # Gestión de bodegas
│   ├── almacenes.php          # Gestión de almacenes
│   ├── productos.php          # Gestión de productos
│   ├── generar-carta.php      # Formulario de carta
│   ├── procesar-carta.php     # Procesador de cartas
│   ├── cartas-entrega.php     # Listado de cartas
│   ├── ver-carta-pdf.php      # Visualizador de PDF
│   ├── acciones-carta.php     # Acciones AJAX
│   ├── historial.php          # Historial de movimientos
│   └── reportes.php           # Reportes y estadísticas
├── logout.php                 # Cerrar sesión
├── estilo.md                  # Guía de estilos
└── README.md                  # Este archivo
```

## 🗄️ Base de Datos

### Tablas Principales

1. **bodegas** - Almacena información de las bodegas
2. **almacenes** - Almacenes dentro de las bodegas
3. **productos_almacen** - Productos del inventario
4. **cartas_entrega** - Cartas de entrega generadas
5. **historial_movimientos** - Registro de todos los movimientos

### Relaciones
- Una bodega puede tener múltiples almacenes
- Un almacén puede contener múltiples productos
- Un producto puede tener múltiples cartas de entrega
- Todos los movimientos se registran en el historial

## 🚀 Instalación

1. **Importar Base de Datos**
   ```sql
   mysql -u usuario -p nombre_bd < almacen/db/almacen.sql
   ```

2. **Configurar Conexión**
   - Editar `almacen/includes/db.php`
   - Ajustar credenciales de base de datos

3. **Permisos**
   ```bash
   chmod 755 almacen/modules/
   chmod 644 almacen/modules/*.php
   ```

4. **Acceder al Sistema**
   - URL: `http://tu-dominio.com/almacen/modules/inicio.php`
   - Requiere autenticación previa

## 📋 Uso del Sistema

### Crear una Bodega
1. Ir a "Bodegas" en el menú
2. Clic en "Nueva Bodega"
3. Llenar formulario con información requerida
4. Guardar

### Crear un Almacén
1. Ir a "Almacenes" en el menú
2. Clic en "Nuevo Almacén"
3. Seleccionar bodega padre
4. Completar información del almacén
5. Guardar

### Agregar Productos
1. Ir a "Productos" en el menú
2. Clic en "Nuevo Producto"
3. Llenar todos los campos requeridos
4. Definir stock mínimo y máximo
5. Guardar

### Generar Carta de Entrega
1. Ir a "Generar Carta"
2. Llenar información del receptor
3. Seleccionar productos a entregar
4. Especificar cantidades
5. Generar PDF

## 🔒 Seguridad

- Validación de sesión en todas las páginas
- Limpieza de datos de entrada (SQL Injection)
- Escapado de salida (XSS)
- Preparación de consultas (Prepared Statements)
- Control de acceso por autenticación

## 📱 Características Responsivas

### Mobile (hasta 768px)
- Menú hamburguesa desplegable
- Tablas con scroll horizontal
- Columnas ocultas en vistas pequeñas
- Botones de ancho completo
- Grid adaptativo a 1 columna

### Tablet (769px - 1024px)
- Menú desplegable similar a móvil
- Grid de 2 columnas
- Tablas optimizadas

### Desktop (1025px+)
- Sidebar fijo y visible
- Grid de 3-4 columnas
- Tablas completas
- Todos los elementos visibles

## 🔧 Tecnologías Utilizadas

- **Backend**: PHP 7.4+
- **Base de Datos**: MySQL 5.7+
- **PDF**: TCPDF
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Estilos**: Tailwind CSS (CDN), CSS personalizado
- **Iconos**: Font Awesome 6.0

## 📊 Funcionalidades Avanzadas

### Dashboard Inteligente
- Estadísticas en tiempo real
- Gráficos de ocupación
- Alertas de stock bajo
- Actividad reciente
- Productos más solicitados

### Filtros Avanzados
- Búsqueda en tiempo real
- Filtros múltiples
- Ordenamiento de columnas
- Exportación de datos

### Gestión de Estados
- Control de estados de productos
- Estados de cartas de entrega
- Estados de almacenes
- Trazabilidad completa

## 🐛 Solución de Problemas

### Error de conexión a BD
- Verificar credenciales en `db.php`
- Verificar que el servidor MySQL esté activo
- Revisar permisos del usuario de BD

### PDF no se genera
- Verificar que TCPDF esté instalado
- Revisar permisos de escritura
- Verificar ruta de TCPDF en `procesar-carta.php`

### Sidebar no responde
- Verificar que `main.js` esté cargado
- Revisar console del navegador
- Verificar compatibilidad del navegador

## 📞 Soporte

Para soporte técnico o reportar problemas:
- Sistema desarrollado para SecuríTI
- Documentación completa en este archivo

## 📝 Changelog

### Versión 1.0.0 (2025-10-15)
- ✅ Sistema completo de gestión de almacén
- ✅ CRUD de bodegas, almacenes y productos
- ✅ Generación de cartas de entrega en PDF
- ✅ Historial de movimientos
- ✅ Reportes y estadísticas
- ✅ Diseño 100% responsivo
- ✅ Dashboard interactivo

## 📄 Licencia

Sistema propietario desarrollado para SecuríTI.
Todos los derechos reservados © 2025

---

**Desarrollado con ❤️ para SecuríTI**

