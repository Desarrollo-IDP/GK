# Sistema de Contadores e Identificadores Únicos para Reportes

## 📋 Índice
1. [Descripción General](#descripción-general)
2. [Propósito del Sistema](#propósito-del-sistema)
3. [Componentes del Sistema](#componentes-del-sistema)
4. [Funcionamiento Detallado](#funcionamiento-detallado)
5. [Archivos Utilizados](#archivos-utilizados)
6. [Flujo del Proceso](#flujo-del-proceso)
7. [Ejemplos Prácticos](#ejemplos-prácticos)
8. [Formato de Identificadores](#formato-de-identificadores)

---

## 📌 Descripción General

El sistema de contadores de reportes es un mecanismo que asigna un **identificador único** a cada reporte generado (Carta Responsiva, Carta de Entrega, Carta de Préstamo). Este identificador funciona como un número de secuencia que permite rastrear y referenciar cada documento generado.

**⚠️ IMPORTANTE**: El sistema es **independiente por empresa de usuario (sucursal)**. Cada empresa tiene sus propios contadores y archivos de mapeo, lo que significa que diferentes empresas pueden tener el mismo número de reporte sin conflictos.

---

## 🎯 Propósito del Sistema

### Objetivos Principales:
1. **Identificación Única**: Cada reporte tiene un número único que lo identifica dentro de su empresa
2. **Trazabilidad**: Permite rastrear qué reportes se han generado por empresa
3. **Evitar Duplicados**: Si se genera el mismo reporte con los mismos equipos y empresa, se reutiliza el mismo número
4. **Contadores Independientes**: Cada tipo de reporte tiene su propio contador independiente
5. **Aislamiento por Empresa**: Cada empresa (sucursal) tiene sus propios contadores y archivos, permitiendo que diferentes empresas tengan el mismo número de reporte sin conflictos

---

## 🔧 Componentes del Sistema

### 1. Función Principal: `obtenerNumeroSecuencia()`

Esta función es el corazón del sistema. Se encarga de:
- Generar una clave única basada en el tipo de reporte, empresa y equipos
- Verificar si ya existe un número asignado para esa combinación
- Si existe, devolver el número existente (evita duplicados)
- Si no existe, generar un nuevo número y guardarlo

**Ubicación**: `Inventario/crud/generar_reporte.php` (líneas 22-45)

**Parámetros**:
- `$tipo`: Tipo de reporte ('carta_responsiva', 'carta_entrega', 'carta_prestamo')
- `$empresa_nombre`: Nombre de la empresa
- `$equipos_ids`: Array con los IDs de los equipos incluidos
- `$csv_file`: Archivo CSV donde se guarda el mapeo de reportes (específico por empresa)
- `$generarNumeroFn`: Función que genera el número secuencial
- `$id_empresa_usuario`: ID de la empresa del usuario (sucursal) - **NUEVO**

**Retorna**: El número de reporte (ej: "CR0-1", "CE0-5", "CP0-3")

**Cambio Importante**: Ahora incluye `$id_empresa_usuario` en la generación de la clave MD5, lo que hace que cada empresa tenga su propio conjunto de reportes independientes.

### 2. Funciones Generadoras de Números

Cada tipo de reporte tiene su propia función que genera números secuenciales:

#### `generarNumeroReporte($id_empresa_usuario)` - Carta Responsiva
- **Archivo contador**: `contador_reporte_responsiva_empresa_{id}.txt` (específico por empresa)
- **Formato**: `CR0-{número}`
- **Ejemplo**: CR0-1, CR0-2, CR0-3...
- **Parámetro**: `$id_empresa_usuario` - ID de la empresa del usuario

#### `generarNumeroReporte2($id_empresa_usuario)` - Carta de Entrega
- **Archivo contador**: `contador_reporte_entrega_empresa_{id}.txt` (específico por empresa)
- **Formato**: `CE0-{número}`
- **Ejemplo**: CE0-1, CE0-2, CE0-3...
- **Parámetro**: `$id_empresa_usuario` - ID de la empresa del usuario

#### `generarNumeroReporte3($id_empresa_usuario)` - Carta de Préstamo
- **Archivo contador**: `contador_reporte_prestamo_empresa_{id}.txt` (específico por empresa)
- **Formato**: `CP0-{número}`
- **Ejemplo**: CP0-1, CP0-2, CP0-3...
- **Parámetro**: `$id_empresa_usuario` - ID de la empresa del usuario

**⚠️ Cambio Importante**: Cada empresa tiene sus propios archivos de contador. Si `$id_empresa_usuario` es `null` (admin), se usa el archivo global sin sufijo.

**Ubicación**: `Inventario/crud/generar_reporte.php` (líneas 176-216)

---

## 📁 Archivos Utilizados

### Archivos de Contador (`.txt`)
Almacenan el número actual del contador para cada tipo de reporte **por empresa**:

1. **`contador_reporte_responsiva_empresa_{id}.txt`**
   - Contiene solo un número (ej: "5")
   - Se incrementa cada vez que se genera un nuevo número
   - Un archivo por cada empresa (ej: `contador_reporte_responsiva_empresa_1.txt`, `contador_reporte_responsiva_empresa_2.txt`)

2. **`contador_reporte_entrega_empresa_{id}.txt`**
   - Contiene solo un número (ej: "3")
   - Se incrementa cada vez que se genera un nuevo número
   - Un archivo por cada empresa

3. **`contador_reporte_prestamo_empresa_{id}.txt`**
   - Contiene solo un número (ej: "7")
   - Se incrementa cada vez que se genera un nuevo número
   - Un archivo por cada empresa

**Ubicación**: `Inventario/crud/`

**Nota**: Si el usuario es administrador (`id_empresa_usuario = null`), se usan archivos sin sufijo de empresa (compatibilidad hacia atrás).

### Archivos de Mapeo (`.csv`)
Almacenan la relación entre combinaciones de reportes y sus números asignados **por empresa**:

1. **`reportes_generados_responsiva_empresa_{id}.csv`**
   - Formato: `{hash_md5},{número_reporte}`
   - Ejemplo: `a1b2c3d4e5f6...,CR0-1`
   - Un archivo por cada empresa (ej: `reportes_generados_responsiva_empresa_1.csv`)

2. **`reportes_generados_entrega_empresa_{id}.csv`**
   - Formato: `{hash_md5},{número_reporte}`
   - Ejemplo: `f6e5d4c3b2a1...,CE0-2`
   - Un archivo por cada empresa

3. **`reportes_generados_prestamo_empresa_{id}.csv`**
   - Formato: `{hash_md5},{número_reporte}`
   - Ejemplo: `1234567890ab...,CP0-5`
   - Un archivo por cada empresa

**Ubicación**: `Inventario/crud/`

**Nota**: Si el usuario es administrador (`id_empresa_usuario = null`), se usa el archivo sin sufijo de empresa (compatibilidad hacia atrás).

---

## 🔄 Funcionamiento Detallado

### Paso 1: Generación de Clave Única

Cuando se solicita generar un reporte, el sistema crea una clave única usando MD5 que **incluye el ID de la empresa del usuario**:

```php
$empresa_key = $id_empresa_usuario !== null ? $id_empresa_usuario : 'global';
$reporte_key = md5($tipo . $empresa_nombre . implode(',', $equipos_ids) . $empresa_key);
```

**Ejemplo**:
- Tipo: `'carta_responsiva'`
- Empresa: `'Empresa ABC'`
- Equipos: `[1, 2, 3]`
- ID Empresa Usuario: `2`
- Resultado: `md5('carta_responsiva' . 'Empresa ABC' . '1,2,3' . '2')` = `a1b2c3d4e5f6...`

**Importante**: El mismo reporte generado por diferentes empresas tendrá claves diferentes, permitiendo que cada empresa tenga su propia secuencia independiente.

### Paso 2: Verificación de Existencia

El sistema lee el archivo CSV correspondiente y busca si ya existe una entrada con esa clave:

```php
if (isset($reporte_map[$reporte_key])) {
    // El reporte ya existe, devolver el número existente
    $numero_reporte = $reporte_map[$reporte_key];
}
```

**Ventaja**: Si se genera el mismo reporte (misma empresa, mismos equipos), se reutiliza el mismo número.

### Paso 3: Generación de Nuevo Número

Si no existe, se llama a la función generadora correspondiente pasando el ID de la empresa:

```php
$numero_reporte = $generarNumeroFn($id_empresa_usuario);
```

Cada función generadora:
1. Crea el nombre del archivo específico por empresa: `contador_reporte_{tipo}_empresa_{id}.txt`
2. Lee el archivo `.txt` del contador (específico para esa empresa)
3. Si no existe, lo crea con valor inicial `0`
4. Si existe, lee el número, lo incrementa y lo guarda
5. Retorna el número formateado (ej: "CR0-5")

**Ventaja**: Cada empresa tiene su propio contador, por lo que la Empresa 1 puede tener CR0-1, CR0-2, y la Empresa 2 también puede tener CR0-1, CR0-2 sin conflictos.

### Paso 4: Guardado del Mapeo

Se guarda la relación clave-número en el archivo CSV:

```php
$reporte_map[$reporte_key] = $numero_reporte;
file_put_contents($csv_file, implode("\n", $lines));
```

---

## 🔀 Flujo del Proceso

```
┌─────────────────────────────────────────────────────────────┐
│  Usuario solicita generar reporte (Carta Responsiva)       │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  obtenerNumeroSecuencia()                                   │
│  - Genera clave MD5: tipo + empresa + equipos               │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  ¿Existe clave en reportes_generados_responsiva.csv?        │
└───────┬───────────────────────────────┬─────────────────────┘
        │                               │
        │ SÍ                            │ NO
        ▼                               ▼
┌───────────────────┐         ┌──────────────────────────────┐
│ Retornar número   │         │ generarNumeroReporte()       │
│ existente         │         │ - Leer contador               │
│ (reutilizar)      │         │ - Incrementar                 │
└───────────────────┘         │ - Guardar                     │
                               │ - Formatear: CR0-{número}     │
                               └───────────┬──────────────────┘
                                           │
                                           ▼
                               ┌──────────────────────────────┐
                               │ Guardar en CSV:               │
                               │ clave,CR0-{número}            │
                               └───────────┬──────────────────┘
                                           │
                                           ▼
                               ┌──────────────────────────────┐
                               │ Retornar número al reporte   │
                               └──────────────────────────────┘
```

---

## 💡 Ejemplos Prácticos

### Ejemplo 1: Primera Generación de Carta Responsiva (Empresa 1)

**Escenario**: Usuario de Empresa 1 genera Carta Responsiva para "Empresa ABC" con equipos [1, 2, 3]

1. **ID Empresa Usuario**: `1`
2. **Clave generada**: `md5('carta_responsivaEmpresa ABC1,2,31')` = `abc123...` (incluye ID empresa)
3. **Archivo CSV**: `reportes_generados_responsiva_empresa_1.csv` (específico para empresa 1)
4. **Verificación CSV**: No existe `abc123...` en el CSV de empresa 1
5. **Archivo contador**: `contador_reporte_responsiva_empresa_1.txt` (específico para empresa 1)
6. **Lectura contador**: Archivo no existe o contiene "0"
7. **Nuevo número**: Se crea/incrementa a "1", se formatea como "CR0-1"
8. **Guardado**: Se agrega `abc123...,CR0-1` al CSV de empresa 1
9. **Resultado**: El reporte muestra "CR0-1"

### Ejemplo 2: Generación Duplicada (Mismo Reporte, Misma Empresa)

**Escenario**: Usuario de Empresa 1 genera nuevamente Carta Responsiva para "Empresa ABC" con equipos [1, 2, 3]

1. **ID Empresa Usuario**: `1`
2. **Clave generada**: `md5('carta_responsivaEmpresa ABC1,2,31')` = `abc123...` (misma clave)
3. **Archivo CSV**: `reportes_generados_responsiva_empresa_1.csv`
4. **Verificación CSV**: Existe `abc123...,CR0-1` en el CSV de empresa 1
5. **Resultado**: Se retorna "CR0-1" (mismo número, no se incrementa el contador)

### Ejemplo 3: Nuevo Reporte con Diferentes Equipos (Misma Empresa)

**Escenario**: Usuario de Empresa 1 genera Carta Responsiva para "Empresa ABC" con equipos [4, 5]

1. **ID Empresa Usuario**: `1`
2. **Clave generada**: `md5('carta_responsivaEmpresa ABC4,51')` = `xyz789...` (diferente)
3. **Archivo CSV**: `reportes_generados_responsiva_empresa_1.csv`
4. **Verificación CSV**: No existe `xyz789...` en el CSV de empresa 1
5. **Archivo contador**: `contador_reporte_responsiva_empresa_1.txt`
6. **Lectura contador**: Contiene "1"
7. **Nuevo número**: Se incrementa a "2", se formatea como "CR0-2"
8. **Guardado**: Se agrega `xyz789...,CR0-2` al CSV de empresa 1
9. **Resultado**: El reporte muestra "CR0-2"

### Ejemplo 4: Diferente Empresa (Mismo Tipo y Equipos)

**Escenario**: Usuario de Empresa 2 genera Carta Responsiva para "Empresa ABC" con equipos [1, 2, 3]

1. **ID Empresa Usuario**: `2` (diferente empresa)
2. **Clave generada**: `md5('carta_responsivaEmpresa ABC1,2,32')` = `def456...` (diferente por empresa)
3. **Archivo CSV**: `reportes_generados_responsiva_empresa_2.csv` (archivo diferente)
4. **Archivo contador**: `contador_reporte_responsiva_empresa_2.txt` (contador independiente)
5. **Lectura contador**: Archivo no existe o contiene "0"
6. **Nuevo número**: Se crea/incrementa a "1", se formatea como "CR0-1"
7. **Resultado**: El reporte muestra "CR0-1" (mismo número que Empresa 1, pero en archivo diferente)

**Ventaja**: Cada empresa puede tener su propia secuencia independiente, permitiendo que múltiples empresas trabajen simultáneamente sin conflictos.

---

## 📝 Formato de Identificadores

### Carta Responsiva
- **Prefijo**: `CR0-`
- **Formato**: `CR0-{número}`
- **Ejemplos**: CR0-1, CR0-2, CR0-10, CR0-100

### Carta de Entrega
- **Prefijo**: `CE0-`
- **Formato**: `CE0-{número}`
- **Ejemplos**: CE0-1, CE0-2, CE0-10, CE0-100

### Carta de Préstamo
- **Prefijo**: `CP0-`
- **Formato**: `CP0-{número}`
- **Ejemplos**: CP0-1, CP0-2, CP0-10, CP0-100

---

## 🔍 Características Importantes

### ✅ Ventajas del Sistema

1. **Evita Duplicados**: El mismo reporte siempre tiene el mismo número dentro de la misma empresa
2. **Contadores Independientes**: Cada tipo de reporte mantiene su propia secuencia
3. **Aislamiento por Empresa**: Cada empresa (sucursal) tiene sus propios contadores y archivos
4. **Escalabilidad**: Múltiples empresas pueden trabajar simultáneamente sin conflictos
5. **Persistencia**: Los números se mantienen aunque se reinicie el servidor
6. **Trazabilidad**: Se puede rastrear qué reportes se han generado por empresa
7. **Simplicidad**: Usa archivos de texto simples, fácil de mantener
8. **Compatibilidad**: Los administradores pueden seguir usando archivos globales

### ⚠️ Consideraciones

1. **Archivos de Texto**: Los contadores se almacenan en archivos `.txt` simples
2. **Archivos CSV**: El mapeo se guarda en archivos CSV
3. **Sincronización**: En entornos multi-usuario, podría haber condiciones de carrera (pero es poco probable)
4. **Backup**: Es importante hacer backup de estos archivos para mantener la secuencia

---

## 🛠️ Mantenimiento

### Ver Contadores Actuales

```bash
# Ver contador de Carta Responsiva para Empresa 1
cat Inventario/crud/contador_reporte_responsiva_empresa_1.txt

# Ver contador de Carta Responsiva para Empresa 2
cat Inventario/crud/contador_reporte_responsiva_empresa_2.txt

# Ver contador de Carta de Entrega para Empresa 1
cat Inventario/crud/contador_reporte_entrega_empresa_1.txt

# Ver contador de Carta de Préstamo para Empresa 1
cat Inventario/crud/contador_reporte_prestamo_empresa_1.txt

# Ver todos los contadores de una empresa
ls Inventario/crud/contador_reporte_*_empresa_1.txt
```

### Ver Reportes Generados

```bash
# Ver todos los reportes de Carta Responsiva generados por Empresa 1
cat Inventario/crud/reportes_generados_responsiva_empresa_1.csv

# Ver todos los reportes de Carta Responsiva generados por Empresa 2
cat Inventario/crud/reportes_generados_responsiva_empresa_2.csv

# Ver todos los reportes de Carta de Entrega generados por Empresa 1
cat Inventario/crud/reportes_generados_entrega_empresa_1.csv

# Ver todos los reportes de Carta de Préstamo generados por Empresa 1
cat Inventario/crud/reportes_generados_prestamo_empresa_1.csv

# Ver todos los archivos de reportes de una empresa
ls Inventario/crud/reportes_generados_*_empresa_1.csv
```

### Reiniciar Contador (Cuidado)

Si necesitas reiniciar un contador:

```bash
# Reiniciar contador de Carta Responsiva a 0
echo "0" > Inventario/crud/contador_reporte_responsiva.txt

# O iniciar desde un número específico
echo "10" > Inventario/crud/contador_reporte_responsiva.txt
```

**⚠️ Advertencia**: Reiniciar un contador puede causar números duplicados si no se limpia también el CSV correspondiente.

---

## 📊 Estructura de Datos

### Archivo CSV de Mapeo

```
{hash_md5_1},{número_reporte_1}
{hash_md5_2},{número_reporte_2}
{hash_md5_3},{número_reporte_3}
```

**Ejemplo real**:
```
a1b2c3d4e5f6789012345678901234567890,CR0-1
x9y8z7w6v5u4321098765432109876543210,CR0-2
m5n4o3p2q1r0987654321098765432109876,CR0-3
```

### Archivo TXT de Contador

```
5
```

Solo contiene un número que representa el último contador usado.

---

## 🔗 Integración con el Sistema

### Uso en el Código

```php
// En generar_reporte.php (línea 73)
// Crear archivo CSV específico por empresa
$csv_file_responsiva = $user_empresa_id !== null 
    ? 'reportes_generados_responsiva_empresa_' . $user_empresa_id . '.csv' 
    : 'reportes_generados_responsiva.csv';

$num_resp = obtenerNumeroSecuencia(
    'carta_responsiva',           // Tipo de reporte
    $empresa_nombre,              // Nombre de la empresa
    $equipos_ids,                 // Array de IDs de equipos
    $csv_file_responsiva,         // Archivo CSV (específico por empresa)
    'generarNumeroReporte',       // Función generadora
    $user_empresa_id              // ID de la empresa del usuario (NUEVO)
);

// El número se pasa a la función de generación
generarCartaResponsiva($equipos, $empresa_nombre, $num_resp);
```

### Uso en las Plantillas

En las plantillas de reporte (ej: `Plantilla_carta_responsiva.php`), el número se muestra usando la variable `$numeroReporte`:

```php
<?php echo htmlspecialchars($numeroReporte); ?>
```

---

## 📌 Resumen

El sistema de contadores de reportes es un mecanismo inteligente que:

1. **Asigna números únicos** a cada combinación de reporte (tipo + empresa + equipos + empresa_usuario)
2. **Reutiliza números** cuando se genera el mismo reporte nuevamente dentro de la misma empresa
3. **Mantiene contadores independientes** para cada tipo de reporte y cada empresa
4. **Aísla por empresa**: Cada empresa (sucursal) tiene sus propios archivos y contadores
5. **Permite múltiples empresas**: Diferentes empresas pueden tener el mismo número de reporte sin conflictos
6. **Persiste la información** en archivos de texto simples específicos por empresa
7. **Permite trazabilidad** de todos los reportes generados por empresa

Este sistema asegura que cada documento tenga un identificador único y consistente dentro de su empresa, facilitando la gestión y referencia de los reportes generados en el sistema de inventario, mientras permite que múltiples empresas trabajen de forma independiente y simultánea.

---

## 🔄 Cambios Implementados (Versión Multi-Empresa)

### Modificaciones Realizadas:

1. **Función `obtenerNumeroSecuencia()`**:
   - Ahora acepta `$id_empresa_usuario` como parámetro
   - Incluye el ID de empresa en la generación de la clave MD5
   - Usa archivos CSV específicos por empresa

2. **Funciones Generadoras**:
   - `generarNumeroReporte($id_empresa_usuario)`
   - `generarNumeroReporte2($id_empresa_usuario)`
   - `generarNumeroReporte3($id_empresa_usuario)`
   - Todas ahora crean archivos de contador específicos por empresa

3. **Estructura de Archivos**:
   - Contadores: `contador_reporte_{tipo}_empresa_{id}.txt`
   - CSV: `reportes_generados_{tipo}_empresa_{id}.csv`
   - Compatibilidad: Administradores siguen usando archivos sin sufijo

### Beneficios del Sistema Multi-Empresa:

- ✅ **Aislamiento Total**: Cada empresa tiene sus propios contadores
- ✅ **Sin Conflictos**: Múltiples empresas pueden tener CR0-1 simultáneamente
- ✅ **Escalabilidad**: Fácil agregar nuevas empresas sin afectar las existentes
- ✅ **Trazabilidad**: Se puede rastrear reportes por empresa específica
- ✅ **Compatibilidad**: Los administradores mantienen funcionalidad global

