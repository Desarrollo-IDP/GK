# Solución para Error "Plan no encontrado" y "Column not found: activo"

## Problemas Identificados

1. **Error "Plan no encontrado"**: Ocurre cuando el QR no puede encontrar un plan válido en la base de datos
2. **Error "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'activo'"**: Ocurre porque el código intenta consultar una columna `activo` que no existe en la tabla `tipos_membresia`

## Solución Implementada

### 1. Corrección de la Columna `activo` → `is_active`

**Problema**: El código estaba intentando consultar una columna llamada `activo` en la tabla `tipos_membresia`, pero según el esquema de la base de datos, la columna se llama `is_active`.

**Archivos Corregidos**:
- `membresias/create_subscription.php`
- `membresias/dashboard.php`
- `membresias/test_qr_dashboard.php`
- `membresias/check_database.php`
- `membresias/create_test_plan.php`
- `membresias/debug_qr_url.php`
- `membresias/test_subscription_flow.php`
- `membresias/test_simplified_flow.php`
- `membresias/test_qr_functionality.php`
- `membresias/test_qr_correction.php`
- `membresias/test_dashboard_real.php`
- `membresias/test_dashboard_flow.php`

**Cambios Realizados**:
- `WHERE activo = 1` → `WHERE is_active = 1`
- `SELECT ..., activo FROM` → `SELECT ..., is_active FROM`
- `$plan['activo']` → `$plan['is_active']`

### 2. Verificación de la Corrección

Para verificar que las correcciones funcionan, ejecuta:
```
http://tu-dominio.com/membresias/test_fix_activo.php
```

Este archivo realizará pruebas para confirmar que:
- ✅ Las consultas con `is_active` funcionan correctamente
- ✅ Los filtros por `is_active = 1` funcionan
- ✅ La estructura de la tabla es correcta
- ✅ Las consultas simuladas de `create_subscription.php` funcionan

## Pasos para Resolver el Error

### Paso 1: Verificar la Corrección
1. Accede a `membresias/test_fix_activo.php`
2. Confirma que todos los tests muestran ✅

### Paso 2: Probar el QR
1. Ve al dashboard principal: `membresias/dashboard.php`
2. Genera un QR para un cliente
3. Escanea el QR
4. Debería redirigir correctamente a Mercado Pago sin errores

### Paso 3: Verificar Planes Disponibles
Si aún hay problemas, verifica que existan planes en la base de datos:

```sql
SELECT id, nombre, precio, duracion_dias, is_active 
FROM tipos_membresia 
ORDER BY id;
```

### Paso 4: Crear Plan de Prueba (si es necesario)
Si no hay planes, ejecuta:
```
http://tu-dominio.com/membresias/create_test_plan.php
```

## Estructura Correcta de la Tabla

La tabla `tipos_membresia` debe tener esta estructura:
```sql
CREATE TABLE `tipos_membresia` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `empresa_id` int DEFAULT '1',
  `precio` decimal(10,2) DEFAULT NULL,
  `duracion_dias` int DEFAULT '30',
  `interval` enum('day','week','month','year') DEFAULT 'month',
  `interval_count` int DEFAULT '1',
  `mp_product_id` varchar(255) DEFAULT NULL,
  `mp_subscription_id` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',  -- ← Esta es la columna correcta
  `mp_plan_id` varchar(255) DEFAULT NULL,
  `monto_cancelacion` decimal(10,2) DEFAULT '0.00'
);
```

## Archivos de Debug Disponibles

1. **`membresias/debug_qr_url.php`**: Inspecciona los parámetros del QR
2. **`membresias/check_database.php`**: Verifica la conexión y datos de la BD
3. **`membresias/test_fix_activo.php`**: Verifica las correcciones de la columna

## Flujo de Suscripción Corregido

1. **Dashboard** (`membresias/dashboard.php`):
   - Busca planes con `is_active = 1`
   - Genera QR con datos del plan existente

2. **Crear Suscripción** (`membresias/create_subscription.php`):
   - Valida el plan con `is_active = 1`
   - Crea preferencia de pago en Mercado Pago
   - Redirige al usuario al checkout

3. **Asociación con Planes Existentes**:
   - El sistema usa planes existentes de `modules/tipos_membresias`
   - No crea nuevos planes automáticamente
   - Respeta la configuración de `is_active`

## Notas Importantes

- ✅ **No se crean nuevos planes**: El sistema usa planes existentes
- ✅ **Columna correcta**: Se usa `is_active` en lugar de `activo`
- ✅ **Validación de estado**: Solo planes activos (`is_active = 1`) son válidos
- ✅ **Debug completo**: Múltiples archivos de debug disponibles

## Próximos Pasos

1. Ejecuta `test_fix_activo.php` para verificar las correcciones
2. Prueba el QR en el dashboard principal
3. Si hay problemas, usa los archivos de debug para identificar la causa
4. Verifica que los planes en `modules/tipos_membresias` estén configurados correctamente
