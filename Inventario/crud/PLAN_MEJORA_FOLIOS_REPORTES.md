## Objetivo

Diseñar un plan para **controlar mejor los identificadores (folios)** de los reportes y cartas generadas en el módulo de inventario, evitando folios duplicados y manteniendo el esquema actual (por tipo de carta y, en algunos casos, por empresa), pero con una solución más robusta y centralizada.

---

## 0. Requerimiento funcional prioritario (folio estable por equipo)

El sistema debe cumplir lo siguiente para las **cartas asociadas a equipos** (responsiva, entrega, préstamo; y un criterio equivalente para mantenimiento manual):

| Escenario | Comportamiento esperado |
|-----------|-------------------------|
| **Refresco de página** después de haber generado la carta (F5, recarga del navegador, repetir la misma petición con los mismos equipos y mismo tipo) | **No** debe asignarse un folio nuevo. Debe mostrarse / recuperarse **el mismo folio** que ya se asignó en la primera generación de esa combinación. |
| **Nueva generación** de la misma carta para **el mismo equipo** (mismos `id_equipo` seleccionados, mismo `tipo_reporte`, misma empresa de usuario aplicable) | Debe conservarse **el mismo contador / folio** asignado **la primera vez** (un folio “pegado” a esa combinación lógica de carta + equipo(s)). |

**Implicación de diseño:** el folio no se obtiene solo “incrementando un contador global” en cada petición. Primero se calcula una **clave única de negocio** (`reporte_key`) que identifica de forma determinista “esta carta para este(s) equipo(s) (y tipo, categoría si aplica, empresa)”. Si ya existe un registro con esa clave, se **reutiliza el `folio`** guardado; solo si **no** existe, se pide el siguiente número de secuencia y se **persiste** la asociación `reporte_key` → `folio`.

**Qué sí genera un folio nuevo:** cambio de conjunto de equipos (otro `id_equipo` o otra selección), otro tipo de carta, o (para mantenimiento) otra combinación definida en la clave (ej. otro número de serie + cliente + categoría, según acuerdo de negocio).

**Nota sobre `report_id` en el formulario:** el campo oculto `report_id` que ya envía `reporte.php` sirve para correlacionar regeneraciones en la misma sesión de formulario, pero **no debe ser la única** base de la clave si el usuario vuelve días después: la clave estable debe basarse en **equipos + tipo + empresa** (y campos fijos de mantenimiento), para que “volver a sacar la carta de ese equipo” siga devolviendo el mismo folio aunque se haya abierto de nuevo la pantalla.

---

## 1. Estado actual (cómo funciona hoy)

### 1.1 Archivos involucrados

- `reporte.php`  
  - Pantalla de selección de tipo de reporte (`general`, `carta_responsiva`, `carta_entrega`, `carta_mantenimiento`, `carta_prestamo`), filtros y equipos.
  - Envía el formulario a `generar_reporte.php`.

- `generar_reporte.php`  
  - Recibe `POST` de `reporte.php` con:
    - `tipo_reporte`
    - `equipos[]` (según selección)
    - `empresa_nombre`
    - Datos adicionales para carta de mantenimiento.
  - Carga los equipos y, según `tipo_reporte`, llama a:
    - `generarReporteGeneral(...)`
    - `generarCartaResponsiva(...)`
    - `generarCartaEntrega(...)`
    - `generarCartaPrestamo(...)`
    - `generarCartaMantenimiento(...)`
  - Gestiona los **folios** mediante:
    - `generarNumeroReporte()` → `CR-XXX`
    - `generarNumeroReporte2()` → `CE-XXX`
    - `generarNumeroReporte3()` → `CP-XXX`
    - `generarNumeroReporteMantenimiento()` → `CML-XXX`, `CMPC-XXX`, `CMI-XXX`, `CMP-XXX`, `CSL-XXX`
    - `obtenerNumeroSecuencia()` + CSV para algunos tipos.

- Plantillas PDF:
  - `Reportes/plantilla_reporte.php` (reporte general, sin folio)
  - `Reportes/Plantilla_carta_responsiva.php`
  - `Reportes/Plantilla_carta_entrega.php`
  - `Reportes/Plantilla_carta_prestamo.php`
  - `Reportes/Plantilla_carta_entrada.php` (mantenimiento)
  - Todas usan variables globales como `$numeroReporte` para mostrar el folio (por ejemplo, en `Plantilla_carta_responsiva.php` se usa para el título: `Carta Responsiva de Equipos - $numeroReporte`).

- Archivos de contador **.txt**:
  - `contador_reporte_responsiva.txt`
  - `contador_reporte_responsiva_empresa_{id}.txt`
  - `contador_reporte_entrega.txt`
  - `contador_reporte_entrega_empresa_{id}.txt`
  - `contador_reporte_prestamo.txt`
  - `contador_mantenimiento_laptop.txt`, `contador_mantenimiento_pc.txt`, `contador_mantenimiento_impresora.txt`, etc. (según `generarNumeroReporteMantenimiento()`).

- Archivos **CSV** de seguimiento:
  - `reportes_generados_entrega*.csv`
  - `reportes_generados_prestamo*.csv`
  - `reportes_generados_mantenimiento*.csv`

### 1.2 Lógica de folios por tipo

- **Carta responsiva (`carta_responsiva`)**
  - En `generar_reporte.php`:
    - Caso `carta_responsiva` llama directamente a `generarNumeroReporte($user_empresa_id)`.
  - `generarNumeroReporte()`:
    - Usa archivo `contador_reporte_responsiva[_empresa_{id}].txt`.
    - Lee el número, incrementa, guarda y forma el folio `CR-XXX`.
    - Siempre incrementa → **cada generación crea un nuevo folio**.
  - `generarCartaResponsiva()`:
    - Asigna el folio a la global `$numeroReporte`.
    - Incluye `Plantilla_carta_responsiva.php`, que lo usa para título, cuerpo, etc.

- **Carta entrega (`carta_entrega`) / carta préstamo (`carta_prestamo`) / carta mantenimiento (`carta_mantenimiento`)**
  - Usan `obtenerNumeroSecuencia()` + un CSV (`reportes_generados_*.csv`) para:
    - Calcular una **clave de reporte** (`$reporte_key`) en base a:
      - `report_id` del formulario (si viene) + tipo + categoría  
      - o bien hash del contenido (tipo, empresa, lista de equipos, etc.).
    - Revisar si ya existe esa clave en el CSV.
      - Si existe → reutiliza el mismo folio.
      - Si no existe → llama a `generarNumeroReporte2/3/Mantenimiento()` para **obtener el siguiente folio secuencial** y lo guarda en el CSV.
  - De esta forma:
    - Se intenta evitar duplicados cuando el usuario refresca o reenvía el mismo reporte.
    - Pero:
      - Depende de archivos CSV en disco.
      - No es transaccional.
      - Es frágil ante escrituras concurrentes o borrado manual de archivos.

---

## 2. Problemas detectados

- **Riesgo de duplicados**
  - Si se borran o editan manualmente los archivos `.txt` o `.csv`, los folios pueden reiniciarse o asignarse dos veces.
  - No hay bloqueo de concurrencia: dos peticiones simultáneas podrían leer el mismo contador antes de incrementarlo.

- **Fuentes múltiples de verdad**
  - Responsiva usa solo `.txt`; las demás combinan `.txt` + `.csv`.
  - No existe una tabla central en BD con el historial de folios generados.

- **Trazabilidad limitada**
  - Para auditoría, hay que buscar en varios archivos (`log_activity`, CSV, contadores) para entender qué folio se generó, cuándo y para quién.

- **Dificultad para extender a nuevos tipos de reporte**
  - Requiere crear nuevos `.txt` y `.csv`, duplicar lógica y mantener más archivos en disco.

---

## 3. Objetivo de mejora

1. **Cumplir el requerimiento de la sección 0:** folio **estable** por combinación carta + equipo(s) (refresh y regeneraciones posteriores con los mismos criterios).
2. Mantener folios **legibles y secuenciales por tipo y empresa** (`CR-001`, `CE-002`, etc.) cuando corresponda asignar un número **nuevo** (primera vez que aparece esa `reporte_key`).
3. Centralizar la numeración y el mapeo clave → folio en **base de datos**, no en `.txt` o `.csv` como fuente de verdad.
4. Garantizar **unicidad** de `reporte_key` y de asignación de folios con transacciones / restricción `UNIQUE`.
5. Unificar el comportamiento: hoy **carta responsiva** siempre incrementa; el plan exige que pase al **mismo flujo** “buscar por clave → reusar o crear” que el resto de cartas por equipo.

---

## 4. Diseño propuesto

### 4.1 Nuevas tablas en base de datos

1. **Tabla `report_sequences`** (control de contadores)

   - `id` (PK, autoincrement)
   - `tipo_reporte` (ENUM o VARCHAR: `carta_responsiva`, `carta_entrega`, `carta_prestamo`, `carta_mantenimiento`)
   - `categoria_mantenimiento` (nullable, para distinguir Laptop/PC/Impresora/etc.)
   - `id_empresa_usuario` (nullable, para separar contadores por empresa)
   - `ultimo_numero` (INT, último valor usado)
   - `prefijo` (VARCHAR, ej. `CR`, `CE`, `CP`, `CML`, etc.)
   - `updated_at`

   Uso:
   - Una sola fila por combinación `(tipo_reporte, categoria_mantenimiento, id_empresa_usuario)`.
   - Para **obtener el siguiente folio**:
     1. `SELECT ... FOR UPDATE` sobre la fila correspondiente.
     2. Incrementar `ultimo_numero`.
     3. Guardar y construir el código (`{prefijo}-{seq}` con `str_pad`).

2. **Tabla `report_instances`** (mapeo estable clave → folio + historial)

   - `id` (PK, autoincrement)
   - `reporte_key` (VARCHAR o CHAR fijo, **UNIQUE**): identificador determinista de la “instancia lógica” de carta (ver construcción en 4.3).
   - `tipo_reporte` (VARCHAR)
   - `folio` (VARCHAR, ej. `CR-001`) — el mismo valor en todas las regeneraciones con la misma clave.
   - `id_empresa_usuario` (nullable)
   - `empresa_nombre` (opcional, texto al momento de crear el registro)
   - `equipos_ids` (texto normalizado: IDs ordenados y unidos, ej. `12,45`)
   - `categoria_mantenimiento` (nullable)
   - `metadata` (JSON o TEXT opcional: huella de campos manuales para auditoría)
   - `first_generated_at` (timestamp de la primera asignación de folio)
   - `last_generated_at` (timestamp de la última generación PDF con esa clave)
   - `created_by` / `updated_by` (opcional)

   Uso (flujo obligatorio para cumplir sección 0):
   1. Calcular `reporte_key` según 4.3.
   2. `SELECT folio FROM report_instances WHERE reporte_key = ?` (dentro de transacción si aplica).
   3. Si existe fila → **devolver ese `folio`** (refresh / misma carta mismo equipo: mismo número).
   4. Si no existe → incrementar secuencia en `report_sequences`, formar `folio`, **INSERT** en `report_instances` con `reporte_key` y fechas.
   5. Opcional: en regeneraciones, actualizar solo `last_generated_at` (y log de actividad).

### 4.2 Estrategia de numeración recomendada

**Regla principal:** **idempotencia por `reporte_key`**, no “un folio nuevo por cada click”.

- El contador global (`report_sequences`) solo avanza cuando se crea una **nueva fila** en `report_instances` (nueva clave = nueva combinación carta + equipo(s) + empresa).
- Refresco del navegador o nueva generación con la misma clave **no** toca el contador de secuencia para ese caso; solo reutiliza el folio ya guardado.

### 4.3 Construcción de `reporte_key` (propuesta)

Objetivo: clave **estable**, **reproducible** y **única** para el negocio. Ejemplos (definir versión final en implementación):

- **Cartas con selección de equipos** (`carta_responsiva`, `carta_entrega`, `carta_prestamo`):
  - Ordenar `equipos_ids` ascendentemente → cadena `ids = implode(',', sorted_ids)`.
  - Incluir `tipo_reporte` y `id_empresa_usuario` (o valor acordado para “global”).
  - Ejemplo de cadena antes de hash: `carta_responsiva|empresa:5|equipos:12,45`.
  - `reporte_key = hash('sha256', $cadena_normalizada)` almacenado en HEX o truncado según longitud deseada; o un VARCHAR compuesto si se prefiere sin hash (más largo pero depurable).

- **Carta de mantenimiento** (sin `equipos[]` o con lógica manual):
  - Clave basada en `tipo_reporte`, `mantenimiento_categoria`, `id_empresa_usuario`, y campos estables del formulario acordados con negocio (p. ej. `entrada_serie` normalizado + `entrada_cliente` normalizado en mayúsculas sin espacios extra).
  - Documentar explícitamente qué campos definen “el mismo caso” para no reutilizar folios entre dos equipos distintos por error.

- **Reporte general** (`general`): no aplica folio tipo carta en plantilla actual; si en el futuro se asigna folio, definir clave (p. ej. por lista de equipos + empresa).

**Importante:** no usar solo `report_id` del POST como clave **única**, porque al volver a abrir `reporte.php` se genera otro `report_id`; la clave debe depender de **equipos + tipo + empresa** para cumplir “misma carta del mismo equipo = mismo folio siempre”.

---

## 5. Cambios por archivo

### 5.1 `Inventario/crud/generar_reporte.php`

Implementar una función central, por ejemplo **`obtenerOReutilizarFolio($tipo_reporte, $contexto)`**, que:

1. Construya `reporte_key` según 4.3 (equipos ordenados + tipo + empresa; o mantenimiento según campos acordados).
2. Consulte `report_instances` por `reporte_key`.
3. Si hay fila → devolver `folio` existente (cumple refresh y “volver a sacar carta del mismo equipo”).
4. Si no hay fila → llamar a `siguienteSecuenciaBD(...)`, crear fila en `report_instances`, devolver el nuevo `folio`.

Cambios por tipo:

- **Responsiva (`carta_responsiva`)**
  - Sustituir el flujo que **siempre** llama a `generarNumeroReporte()` por el flujo idempotente anterior.
  - Misma función helper; prefijo `CR`.

- **Carta entrega (`carta_entrega`)** / **Carta préstamo (`carta_prestamo`)**
  - Sustituir `obtenerNumeroSecuencia()` + CSV por la misma función helper (prefijos `CE`, `CP`).
  - La clave debe basarse en `equipos[]` normalizados, no en el `report_id` del formulario como único identificador.

- **Carta mantenimiento (`carta_mantenimiento`)**
  - Sustituir CSV por BD; construir `reporte_key` con categoría + empresa + campos manuales definidos (serie, cliente, etc.).

- **Funciones de numeración actuales**
  - `generarNumeroReporte*()` solo deben usarse **dentro** del paso “no existe instancia → asignar siguiente número”, no en cada petición sin consultar `report_instances`.
  - Objetivo final: una sola capa `siguienteSecuenciaBD(...)` + `report_instances` como fuente de verdad del mapeo clave → folio.

### 5.2 Plantillas de cartas en `Inventario/crud/Reportes`

- **`Plantilla_carta_responsiva.php`**
  - Revisar todos los lugares donde se usa `$numeroReporte` (ej. título, cuerpo).
  - No requiere cambio de lógica: solo asegurarse que `generarCartaResponsiva()` siempre establece el folio desde la nueva función BD.

- **`Plantilla_carta_entrega.php`**
- **`Plantilla_carta_prestamo.php`**
- **`Plantilla_carta_entrada.php`**
  - Igual que arriba: verificar uso de `$numeroReporte` y otros textos donde se mencione folio/identificador.
  - Ajustar textos si se desea incluir más información (ej. empresa, fecha) usando datos ya disponibles en las funciones `generarCarta*`.

- **`plantilla_reporte.php`**
  - No usa folio hoy, pero se puede considerar añadir un identificador de reporte (opcional) más adelante usando el mismo mecanismo, si se requiere trazabilidad.

### 5.3 `Inventario/crud/reporte.php`

- El `report_id` oculto puede seguir usándose para **trazabilidad en logs** o depuración, pero la lógica de folio estable debe basarse en **`equipos[]` + `tipo_reporte` + empresa de usuario** (véase 4.3).
- Opcional: mostrar en UI el último folio generado para la selección actual (requiere endpoint o consulta previa; fuera del alcance mínimo del plan).
- Documentar en comentarios qué POST participa en `reporte_key` para que futuros cambios no rompan la idempotencia.

### 5.4 Archivos de contadores y CSV (fase de migración)

- Identificar y planear la **eliminación gradual** de:
  - `contador_reporte_responsiva*.txt`
  - `contador_reporte_entrega*.txt`
  - `contador_reporte_prestamo*.txt`
  - `contador_mantenimiento_*.txt`
  - `reportes_generados_*.csv`

- Script de migración (opcional pero recomendable):
  - Leer los `.txt` actuales para inicializar `report_sequences` con `ultimo_numero` correcto.
  - Leer los `.csv` para poblar parcialmente `report_instances` (historial anterior), si es importante conservarlo.

---

## 6. Pasos de implementación

1. **Diseño de tablas y migración inicial**
   - Crear `report_sequences` y `report_instances`.
   - Migrar valores iniciales desde archivos `.txt`.

2. **Crear helpers de numeración en PHP**
   - Nuevo archivo helper (por ejemplo `includes/report_folio_helper.php`) con:
     - `construirReporteKey($tipo, $equiposIdsOrdenados, $idEmpresaUsuario, $extras)` — implementa 4.3.
     - `obtenerOReutilizarFolio(...)` — consulta `report_instances`, reuso o creación.
     - `siguienteSecuenciaBD(...)` — solo cuando haya que crear nueva instancia.
     - `actualizarUltimaGeneracion($reporteKey)` — opcional, para `last_generated_at`.
   - Incluir este helper en `generar_reporte.php`.

3. **Refactor en `generar_reporte.php`**
   - Cambiar cada `case` de `switch ($tipo_reporte)` para usar la nueva API de folios.
   - Eliminar lógica dependiente de `.csv` y de funciones antiguas.

4. **Ajuste y verificación de plantillas**
   - Confirmar que todas las plantillas reciben `$numeroReporte` correctamente.
   - **Pruebas de aceptación del requerimiento sección 0:**
     - Generar carta para equipo(s) A → anotar folio.
     - Refrescar / repetir generación con mismos equipos y tipo → **mismo folio**.
     - Cerrar navegador, volver a `reporte.php`, seleccionar mismos equipos y generar → **mismo folio**.
     - Cambiar un equipo o tipo de carta → **folio distinto** (nueva clave).

5. **Limpieza de archivos en disco**
   - Una vez validada la nueva lógica en producción:
     - Borrar o archivar `.txt` y `.csv` antiguos.
     - Eliminar funciones de numeración antiguas del código.

6. **Monitoreo y logs**
   - Aprovechar `log_activity.php` para registrar:
     - `tipo_reporte`, `folio`, `id_empresa_usuario`, `equipos_ids`.
   - Cruzar esta información con `report_instances` para auditoría.

---

## 7. Recomendaciones finales

- **Usar base de datos para todo lo crítico de numeración y para el mapeo clave → folio.**  
  Los `.txt` / `.csv` no deben ser la fuente de verdad para “mismo equipo = mismo folio”.

- **Priorizar idempotencia (sección 0) sobre “nuevo folio por cada click”.**  
  La secuencia global solo crece cuando aparece una **nueva** `reporte_key` (nueva combinación negocio).

- **Mantener la secuencia por tipo + empresa** en `report_sequences` para seguir generando `CR-001`, `CE-002`, etc. de forma ordenada cuando sí toca un número nuevo.

- **Condiciones de carrera:** usar transacción con `SELECT … FOR UPDATE` en `report_instances` o `INSERT … ON DUPLICATE KEY` según motor, para que dos peticiones simultáneas con la misma clave no creen dos folios.

- **Documentar el flujo en un diagrama sencillo.**  
  1. Usuario elige tipo y equipos en `reporte.php`.
  2. `generar_reporte.php` calcula `reporte_key`.
  3. Si existe en `report_instances` → mismo `folio`; si no → `report_sequences` + INSERT.
  4. La plantilla muestra `$numeroReporte`.

Con este plan se alinea la implementación con folios **estables al refrescar y al volver a generar la carta del mismo equipo**, además de una numeración controlada en base de datos.

