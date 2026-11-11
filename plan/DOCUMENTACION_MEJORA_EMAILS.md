# Documentación: Sistema de Procesamiento Automático de Emails Multi-Empresa

## 📋 Índice

1. [Resumen General](#resumen-general)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Archivos Creados y Modificados](#archivos-creados-y-modificados)
4. [Flujo de Funcionamiento](#flujo-de-funcionamiento)
5. [Configuración y Uso](#configuración-y-uso)
6. [Manejo de Errores](#manejo-de-errores)
7. [Monitoreo y Logs](#monitoreo-y-logs)

---

## 🎯 Resumen General

Este sistema mejora el procesamiento de emails en un entorno multi-empresa, permitiendo:

- ✅ **Procesamiento Automático**: Ejecución automática cada 5 minutos sin intervención manual
- ✅ **Escalabilidad**: Soporta múltiples configuraciones de email sin saturación
- ✅ **Resiliencia**: Errores en una empresa no afectan a las demás
- ✅ **Control Granular**: Configuración individual por empresa
- ✅ **Monitoreo**: Logging estructurado en base de datos

---

## 🏗️ Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────┐
│                    CRON JOB (Cada 5 minutos)                │
│              tickets/cron/email_processor.php                │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. Obtiene empresas pendientes de procesamiento            │
│ 2. Procesa máximo 3 empresas por ejecución                 │
│ 3. Timeout de 4 minutos por empresa                        │
│ 4. Registra logs en email_processing_log                    │
└────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Por cada empresa (con try-catch):              │
│  ┌────────────────────────────────────────────┐            │
│  │ • Conecta IMAP con configuración empresa  │            │
│  │ • Obtiene correos UNSEEN (máx. 50)       │            │
│  │ • Procesa cada correo                     │            │
│  │ • Registra éxito/error                   │            │
│  │ • Actualiza last_processed_at            │            │
│  └────────────────────────────────────────────┘            │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Archivos Creados y Modificados

### 🆕 Archivos Nuevos

#### 1. `sql/add_email_processing_tables.sql`

**Propósito**: Script de migración SQL para crear las estructuras necesarias en la base de datos.

**Qué hace**:
- Crea la tabla `email_processing_log` para registrar cada ejecución de procesamiento
- Agrega columnas a `empresa_email_config` para control de procesamiento:
  - `last_processed_at`: Última vez que se procesaron emails
  - `processing_enabled`: Habilitar/deshabilitar procesamiento automático
  - `max_emails_per_run`: Máximo de correos por ejecución
  - `processing_interval_minutes`: Intervalo mínimo entre procesamientos
  - `error_count`: Contador de errores consecutivos
  - `last_error`: Último mensaje de error

**Cuándo ejecutarlo**: Una sola vez, antes de usar el sistema.

---

#### 2. `tickets/cron/config_email_processor.php`

**Propósito**: Archivo de configuración con parámetros globales del procesador.

**Parámetros configurables**:
```php
EMAIL_PROCESSOR_MAX_EMPRESAS_PER_RUN = 3        // Máx. empresas por ejecución
EMAIL_PROCESSOR_TIMEOUT_PER_EMPRESA = 240        // Timeout en segundos (4 min)
EMAIL_PROCESSOR_MAX_EMAILS_PER_EMPRESA = 50     // Máx. correos por empresa
EMAIL_PROCESSOR_DEFAULT_INTERVAL = 5             // Intervalo por defecto (minutos)
EMAIL_PROCESSOR_MAX_ERROR_COUNT = 10            // Errores antes de deshabilitar
EMAIL_PROCESSOR_IMAP_CONNECTION_TIMEOUT = 30     // Timeout conexión IMAP
EMAIL_PROCESSOR_DEBUG = false                    // Logging detallado
```

**Cómo funciona**: Define constantes que son utilizadas por el procesador principal.

---

#### 3. `tickets/includes/email_processing_helper.php`

**Propósito**: Funciones helper comunes utilizadas en todo el sistema de procesamiento.

**Funciones principales**:

1. **`safeCloseStmt($stmt)`**
   - Cierra de forma segura un statement de MySQLi
   - Evita errores si el statement ya está cerrado

2. **`ensureDbConnection()`**
   - Verifica y restablece la conexión a la base de datos si es necesario
   - Asegura que la conexión esté activa antes de ejecutar consultas

3. **`logEmailProcessing(...)`**
   - Registra cada ejecución de procesamiento en `email_processing_log`
   - Parámetros: empresa, estado, correos procesados, tiempo, errores
   - Retorna `true` si se registró correctamente

4. **`updateEmpresaProcessingStatus(...)`**
   - Actualiza el estado de procesamiento de una empresa
   - Si éxito: resetea `error_count` y actualiza `last_processed_at`
   - Si error: incrementa `error_count` y deshabilita si alcanza el límite

5. **`getEmpresasPendingProcessing($conn, $max_empresas)`**
   - Obtiene empresas que necesitan procesamiento
   - Filtra por: `activo = 1`, `processing_enabled = 1`, intervalo cumplido
   - Ordena por `last_processed_at` (las más antiguas primero)
   - Retorna máximo `$max_empresas` empresas

6. **`normalizeSpecialCharacters($text)`**
   - Normaliza caracteres especiales en texto
   - Asegura codificación UTF-8 válida
   - Limpia caracteres de control

**Cómo funciona**: Proporciona funciones reutilizables que evitan código duplicado.

---

#### 4. `tickets/cron/email_processor_functions.php`

**Propósito**: Funciones específicas para el procesamiento automático de emails por empresa.

**Funciones principales**:

1. **`processEmpresaEmails($id_empresa, $conn, $max_emails)`**
   - **Qué hace**: Procesa los emails de una empresa específica
   - **Parámetros**:
     - `$id_empresa`: ID de la empresa a procesar
     - `$conn`: Conexión a la base de datos
     - `$max_emails`: Máximo de correos a procesar (default: 50)
   - **Retorna**: Array con:
     - `success`: Si el procesamiento fue exitoso
     - `emails_found`: Número de correos encontrados
     - `emails_processed`: Número de correos procesados exitosamente
     - `error_message`: Mensaje de error si hubo
     - `execution_time`: Tiempo de ejecución en segundos
   - **Cómo funciona**:
     1. Obtiene configuración IMAP para la empresa
     2. Conecta al servidor IMAP
     3. Busca correos UNSEEN (no leídos)
     4. Limita a `$max_emails` correos
     5. Procesa cada correo usando `processEmail()` de `outlook_emails.php`
     6. Cierra la conexión IMAP
     7. Retorna estadísticas

2. **`processEmpresaWithTimeout($empresa_config, $conn)`**
   - **Qué hace**: Procesa emails con timeout y manejo robusto de errores
   - **Parámetros**:
     - `$empresa_config`: Array con configuración de la empresa
     - `$conn`: Conexión a la base de datos
   - **Retorna**: `true` si fue exitoso, `false` si hubo error o timeout
   - **Cómo funciona**:
     1. Configura timeout según `EMAIL_PROCESSOR_TIMEOUT_PER_EMPRESA`
     2. Llama a `processEmpresaEmails()`
     3. Verifica si se alcanzó el timeout
     4. Registra log en base de datos
     5. Actualiza estado de la empresa
     6. Maneja errores sin afectar otras empresas

**Cómo funciona**: Encapsula la lógica de procesamiento con manejo de errores y timeouts.

---

#### 5. `tickets/cron/email_processor.php`

**Propósito**: Script principal que se ejecuta automáticamente mediante cron cada 5 minutos.

**Qué hace**:
1. **Verificaciones iniciales**:
   - Verifica que se ejecute desde CLI o con `?allow_browser=1`
   - Verifica conexión a base de datos
   - Verifica extensión IMAP

2. **Obtiene empresas pendientes**:
   - Llama a `getEmpresasPendingProcessing()`
   - Obtiene máximo 3 empresas (configurable)

3. **Procesa cada empresa**:
   - Por cada empresa, llama a `processEmpresaWithTimeout()`
   - Maneja errores individualmente (una empresa con error no afecta a las demás)
   - Registra estadísticas

4. **Finaliza**:
   - Registra tiempo total de ejecución
   - Sale con código 0 (éxito) o 1 (error crítico)

**Cómo funciona**: Es el punto de entrada principal que orquesta todo el procesamiento.

**Ejecución**:
- Automática: Mediante cron cada 5 minutos
- Manual: `php tickets/cron/email_processor.php`
- Navegador: `http://localhost/tickets/cron/email_processor.php?allow_browser=1`

---

#### 6. `setup_email_cron.bat` (Windows)

**Propósito**: Script para configurar la tarea programada en Windows Task Scheduler.

**Qué hace**:
1. Verifica que PHP existe en la ruta especificada
2. Verifica que el script existe
3. Verifica permisos de administrador
4. Crea la tarea programada "GK_EmailProcessor" que ejecuta el script cada 5 minutos

**Cómo ejecutarlo**:
1. Clic derecho en el archivo
2. Seleccionar "Ejecutar como administrador"
3. El script verificará y creará la tarea automáticamente

**Comandos útiles**:
```batch
# Verificar la tarea
schtasks /query /tn "GK_EmailProcessor"

# Eliminar la tarea
schtasks /delete /tn "GK_EmailProcessor" /f
```

---

#### 7. `setup_email_cron.sh` (Linux)

**Propósito**: Script para configurar el cron job en Linux.

**Qué hace**:
1. Detecta la ruta de PHP automáticamente
2. Verifica que el script existe
3. Agrega entrada al crontab para ejecutar cada 5 minutos
4. Configura logging en `/var/log/email_processor.log`

**Cómo ejecutarlo**:
```bash
chmod +x setup_email_cron.sh
./setup_email_cron.sh
```

**Comandos útiles**:
```bash
# Ver el cron job
crontab -l

# Ver logs
tail -f /var/log/email_processor.log

# Eliminar el cron job
crontab -l | grep -v 'email_processor.php' | crontab -
```

---

### 🔄 Archivos Modificados

#### 1. `tickets/modules/configuration/empresa_email_config.php`

**Cambios realizados**:
- Cambiado `FROM empresas` a `FROM empresas_usuarios` (línea 16)
- Cambiado `LEFT JOIN empresas e` a `LEFT JOIN empresas_usuarios eu` (línea 127)
- Corregido `bind_param` para INSERT y UPDATE (tipos de parámetros)

**Por qué**: Para usar la tabla correcta `empresas_usuarios` en lugar de `empresas`.

**Cómo funciona ahora**:
- Obtiene empresas desde `empresas_usuarios` para el formulario
- Muestra nombre de empresa desde `empresas_usuarios` en las configuraciones existentes
- Mantiene compatibilidad con el resto del sistema

---

#### 2. `tickets/modules/outlook_emails.php`

**Cambios realizados**:
1. **Agregado `require_once` del helper** (línea 8):
   ```php
   require_once(__DIR__ . '/../includes/email_processing_helper.php');
   ```

2. **Eliminadas funciones duplicadas**:
   - `safeCloseStmt()` - Ahora usa la del helper
   - `ensureDbConnection()` - Ahora usa la del helper

3. **Protegida función `normalizeSpecialCharacters()`**:
   - Envuelta en `if (!function_exists())` para evitar redeclaración

4. **Código principal envuelto en condición:
   ```php
   if (!defined('EMAIL_PROCESSOR_MODE')) {
       // Código de ejecución directa (HTML, etc.)
   }
   ```

**Por qué**: 
- Evita redeclaración de funciones cuando se incluye desde el procesador
- Permite usar `outlook_emails.php` como biblioteca de funciones sin ejecutar código HTML
- Mantiene compatibilidad con ejecución directa desde navegador

**Cómo funciona ahora**:
- Cuando se ejecuta directamente: Muestra interfaz HTML y procesa emails
- Cuando se incluye desde procesador: Solo proporciona funciones, no ejecuta código HTML

---

## 🔄 Flujo de Funcionamiento

### Flujo Completo de Procesamiento

```
1. CRON ejecuta email_processor.php cada 5 minutos
   │
   ├─► Verifica conexión BD y extensión IMAP
   │
   ├─► Llama a getEmpresasPendingProcessing()
   │   │
   │   └─► Consulta empresas con:
   │       - activo = 1
   │       - processing_enabled = 1
   │       - last_processed_at NULL o < (NOW() - intervalo)
   │       - Ordenadas por antigüedad
   │       - Máximo 3 empresas
   │
   ├─► Para cada empresa (máximo 3):
   │   │
   │   ├─► Llama a processEmpresaWithTimeout()
   │   │   │
   │   │   ├─► Configura timeout (4 minutos)
   │   │   │
   │   │   ├─► Llama a processEmpresaEmails()
   │   │   │   │
   │   │   │   ├─► Obtiene configuración IMAP de la empresa
   │   │   │   │
   │   │   │   ├─► Conecta a IMAP con configuración específica
   │   │   │   │
   │   │   │   ├─► Busca correos UNSEEN
   │   │   │   │
   │   │   │   ├─► Limita a max_emails_per_run (default: 50)
   │   │   │   │
   │   │   │   ├─► Para cada correo:
   │   │   │   │   └─► Llama a processEmail() de outlook_emails.php
   │   │   │   │       - Extrae información del correo
   │   │   │   │       - Detecta si es respuesta o nuevo ticket
   │   │   │   │       - Crea/actualiza ticket
   │   │   │   │       - Guarda mensaje y adjuntos
   │   │   │   │
   │   │   │   └─► Cierra conexión IMAP
   │   │   │
   │   │   ├─► Verifica timeout
   │   │   │
   │   │   ├─► Registra log en email_processing_log
   │   │   │   - status: success/error/timeout
   │   │   │   - emails_processed
   │   │   │   - emails_found
   │   │   │   - execution_time
   │   │   │   - error_message (si aplica)
   │   │   │
   │   │   └─► Actualiza empresa_email_config
   │   │       - last_processed_at
   │   │       - error_count
   │   │       - processing_enabled (si error_count >= 10)
   │   │
   │   └─► Continúa con siguiente empresa (aunque haya error)
   │
   └─► Finaliza ejecución
```

---

## ⚙️ Configuración y Uso

### Paso 1: Ejecutar Script SQL

```sql
-- Ejecutar en phpMyAdmin o desde línea de comandos
source sql/add_email_processing_tables.sql;
```

**Qué crea**:
- Tabla `email_processing_log`
- Columnas en `empresa_email_config`

---

### Paso 2: Configurar Email por Empresa

1. Acceder a: `tickets/modules/configuration/empresa_email_config.php`
2. Crear nueva configuración:
   - Seleccionar empresa de `empresas_usuarios`
   - Configurar SMTP (envío)
   - Configurar IMAP (recepción)
   - Activar configuración
3. Configurar parámetros opcionales:
   - `max_emails_per_run`: Máximo de correos por ejecución (default: 50)
   - `processing_interval_minutes`: Intervalo mínimo entre procesamientos (default: 5)

---

### Paso 3: Configurar Cron Job

**Windows**:
1. Clic derecho en `setup_email_cron.bat`
2. "Ejecutar como administrador"
3. Verificar que se creó la tarea:
   ```batch
   schtasks /query /tn "GK_EmailProcessor"
   ```

**Linux**:
```bash
chmod +x setup_email_cron.sh
./setup_email_cron.sh
```

---

### Paso 4: Verificar Funcionamiento

**Probar manualmente**:
```bash
# Desde línea de comandos
php tickets/cron/email_processor.php

# O desde navegador
http://localhost/tickets/cron/email_processor.php?allow_browser=1
```

**Verificar logs**:
```sql
-- Últimas ejecuciones
SELECT * FROM email_processing_log 
ORDER BY created_at DESC 
LIMIT 10;

-- Empresas con errores
SELECT id_empresa, error_count, last_error, last_processed_at
FROM empresa_email_config
WHERE error_count > 0;
```

---

## 🛡️ Manejo de Errores

### Tipos de Errores y Cómo se Manejan

1. **Error de Conexión IMAP**:
   - Se registra en `email_processing_log` con status `error`
   - Se incrementa `error_count` en `empresa_email_config`
   - Se continúa con la siguiente empresa
   - Si `error_count >= 10`: Se deshabilita `processing_enabled`

2. **Error al Procesar un Email Individual**:
   - Se registra en logs de PHP
   - Se continúa con el siguiente correo
   - No afecta el procesamiento de otros correos

3. **Timeout**:
   - Se registra en `email_processing_log` con status `timeout`
   - Se cierra la conexión IMAP
   - Se continúa con la siguiente empresa
   - Se incrementa `error_count`

4. **Error Crítico (BD, memoria, etc.)**:
   - Se registra en logs de PHP
   - Se termina la ejecución del script
   - No se procesan más empresas en esa ejecución

---

## 📊 Monitoreo y Logs

### Tabla `email_processing_log`

**Estructura**:
- `id`: ID único del registro
- `id_empresa`: ID de la empresa procesada
- `status`: Estado (`success`, `error`, `timeout`, `skipped`)
- `emails_processed`: Correos procesados exitosamente
- `emails_found`: Correos encontrados
- `execution_time`: Tiempo de ejecución en segundos
- `error_message`: Mensaje de error (si aplica)
- `last_processed_at`: Fecha y hora del procesamiento
- `created_at`: Fecha y hora de creación del registro

**Consultas útiles**:

```sql
-- Últimas ejecuciones exitosas
SELECT id_empresa, emails_processed, execution_time, last_processed_at
FROM email_processing_log
WHERE status = 'success'
ORDER BY last_processed_at DESC
LIMIT 20;

-- Empresas con errores recientes
SELECT id_empresa, COUNT(*) as error_count, MAX(last_processed_at) as last_error
FROM email_processing_log
WHERE status = 'error' 
  AND last_processed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY id_empresa;

-- Rendimiento promedio por empresa
SELECT id_empresa, 
       AVG(emails_processed) as avg_emails,
       AVG(execution_time) as avg_time,
       COUNT(*) as total_runs
FROM email_processing_log
WHERE status = 'success'
GROUP BY id_empresa;

-- Empresas deshabilitadas por errores
SELECT eec.id_empresa, eu.nombre, eec.error_count, eec.last_error
FROM empresa_email_config eec
INNER JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
WHERE eec.processing_enabled = 0
  AND eec.error_count >= 10;
```

---

### Tabla `empresa_email_config` (Columnas Nuevas)

**Columnas agregadas**:

- `last_processed_at`: Última vez que se procesaron emails
- `processing_enabled`: 1=Habilitado, 0=Deshabilitado
- `max_emails_per_run`: Máximo de correos por ejecución
- `processing_interval_minutes`: Intervalo mínimo entre procesamientos
- `error_count`: Contador de errores consecutivos
- `last_error`: Último mensaje de error

**Consultas útiles**:

```sql
-- Empresas activas y su última ejecución
SELECT eec.id_empresa, eu.nombre, 
       eec.last_processed_at, 
       eec.error_count,
       eec.processing_enabled
FROM empresa_email_config eec
INNER JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
WHERE eec.activo = 1
ORDER BY eec.last_processed_at ASC NULLS FIRST;

-- Empresas que necesitan procesamiento
SELECT eec.id_empresa, eu.nombre,
       eec.last_processed_at,
       eec.processing_interval_minutes,
       TIMESTAMPDIFF(MINUTE, eec.last_processed_at, NOW()) as minutos_desde_ultimo
FROM empresa_email_config eec
INNER JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
WHERE eec.activo = 1
  AND eec.processing_enabled = 1
  AND (eec.last_processed_at IS NULL 
       OR eec.last_processed_at < DATE_SUB(NOW(), INTERVAL eec.processing_interval_minutes MINUTE))
ORDER BY eec.last_processed_at ASC NULLS FIRST;
```

---

## 🔧 Configuración Avanzada

### Ajustar Parámetros Globales

Editar `tickets/cron/config_email_processor.php`:

```php
// Procesar más empresas por ejecución
define('EMAIL_PROCESSOR_MAX_EMPRESAS_PER_RUN', 5);

// Aumentar timeout por empresa
define('EMAIL_PROCESSOR_TIMEOUT_PER_EMPRESA', 300); // 5 minutos

// Procesar más correos por empresa
define('EMAIL_PROCESSOR_MAX_EMAILS_PER_EMPRESA', 100);

// Habilitar logging detallado
define('EMAIL_PROCESSOR_DEBUG', true);
```

### Configurar Parámetros por Empresa

Desde `empresa_email_config.php`:
- `max_emails_per_run`: Empresas con muchos correos pueden aumentar este valor
- `processing_interval_minutes`: Empresas críticas pueden reducir el intervalo

### Habilitar/Deshabilitar Procesamiento

```sql
-- Deshabilitar procesamiento para una empresa
UPDATE empresa_email_config 
SET processing_enabled = 0 
WHERE id_empresa = 1;

-- Habilitar procesamiento y resetear errores
UPDATE empresa_email_config 
SET processing_enabled = 1, 
    error_count = 0,
    last_error = NULL
WHERE id_empresa = 1;
```

---

## 🚨 Solución de Problemas

### Problema: No se procesan emails

**Verificar**:
1. ¿Está configurado el cron job?
   ```bash
   # Windows
   schtasks /query /tn "GK_EmailProcessor"
   
   # Linux
   crontab -l
   ```

2. ¿Hay empresas con `processing_enabled = 1`?
   ```sql
   SELECT id_empresa, processing_enabled, activo 
   FROM empresa_email_config;
   ```

3. ¿Se cumplió el intervalo?
   ```sql
   SELECT id_empresa, last_processed_at, processing_interval_minutes,
          TIMESTAMPDIFF(MINUTE, last_processed_at, NOW()) as minutos_desde_ultimo
   FROM empresa_email_config
   WHERE activo = 1 AND processing_enabled = 1;
   ```

---

### Problema: Empresa deshabilitada automáticamente

**Causa**: 10 errores consecutivos

**Solución**:
```sql
-- Ver el último error
SELECT id_empresa, last_error, error_count 
FROM empresa_email_config 
WHERE id_empresa = X;

-- Habilitar nuevamente y resetear errores
UPDATE empresa_email_config 
SET processing_enabled = 1, 
    error_count = 0,
    last_error = NULL
WHERE id_empresa = X;
```

---

### Problema: Timeout frecuente

**Soluciones**:
1. Reducir `max_emails_per_run` para esa empresa
2. Aumentar `EMAIL_PROCESSOR_TIMEOUT_PER_EMPRESA` en `config_email_processor.php`
3. Verificar velocidad de conexión IMAP

---

## 📈 Mejoras Futuras Posibles

1. **Dashboard de Monitoreo**: Interfaz web para ver estado de procesamiento
2. **Notificaciones**: Alertas cuando una empresa se deshabilita
3. **Procesamiento Paralelo**: Procesar múltiples empresas simultáneamente
4. **Reintentos Inteligentes**: Reintentar automáticamente después de errores temporales
5. **Métricas Avanzadas**: Gráficas de rendimiento y estadísticas

---

## 📝 Resumen de Archivos

| Archivo | Tipo | Propósito |
|---------|------|-----------|
| `sql/add_email_processing_tables.sql` | SQL | Migración de base de datos |
| `tickets/cron/config_email_processor.php` | PHP | Configuración global |
| `tickets/includes/email_processing_helper.php` | PHP | Funciones helper comunes |
| `tickets/cron/email_processor_functions.php` | PHP | Funciones de procesamiento |
| `tickets/cron/email_processor.php` | PHP | Script principal (ejecuta cron) |
| `setup_email_cron.bat` | Batch | Configuración Windows |
| `setup_email_cron.sh` | Bash | Configuración Linux |
| `tickets/modules/configuration/empresa_email_config.php` | PHP | Modificado: usa empresas_usuarios |
| `tickets/modules/outlook_emails.php` | PHP | Modificado: funciones compartidas |

---

## ✅ Checklist de Implementación

- [ ] Ejecutar `sql/add_email_processing_tables.sql`
- [ ] Configurar al menos una empresa en `empresa_email_config.php`
- [ ] Probar manualmente: `php tickets/cron/email_processor.php`
- [ ] Configurar cron job (Windows o Linux)
- [ ] Verificar primera ejecución automática
- [ ] Revisar logs en `email_processing_log`
- [ ] Monitorear durante 24 horas

---

**Última actualización**: 2025-01-XX

