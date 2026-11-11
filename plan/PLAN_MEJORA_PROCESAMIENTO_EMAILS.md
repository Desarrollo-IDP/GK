# Plan de Mejora: Sistema de Procesamiento de Emails Multi-Empresa

## 📋 Resumen Ejecutivo

Este documento describe el plan de mejora para optimizar el procesamiento automático de emails en el sistema multi-empresa, permitiendo escalar a múltiples configuraciones sin saturación y garantizando ejecución automática sin intervención manual.

---

## 🔍 Análisis del Estado Actual

### Funcionamiento Actual

1. **Archivo Principal**: `tickets/modules/outlook_emails.php`
   - Procesa todas las empresas en un solo script
   - Ejecuta secuencialmente cada configuración
   - No tiene límite de tiempo ni control de recursos
   - Si una empresa falla, puede afectar a las demás

2. **Ejecución**:
   - Manual mediante `ejecutar_outlook_emails.bat`
   - O mediante acceso directo al archivo PHP
   - No hay sistema de cron programado visible

3. **Problemas Identificados**:
   - ❌ Procesamiento secuencial bloqueante
   - ❌ Sin manejo robusto de errores por empresa
   - ❌ Sin límites de tiempo de ejecución
   - ❌ Sin sistema de reintentos automáticos
   - ❌ Sin logging estructurado de errores
   - ❌ Sin control de concurrencia (múltiples ejecuciones simultáneas)
   - ❌ Sin priorización de empresas
   - ❌ Sin métricas de rendimiento

---

## 🎯 Objetivos de la Mejora

1. ✅ **Procesamiento Automático**: Ejecución automática sin intervención manual
2. ✅ **Escalabilidad**: Soportar 10+ configuraciones sin saturación
3. ✅ **Resiliencia**: Errores en una empresa no afectan a las demás
4. ✅ **Monitoreo**: Logging estructurado y métricas de rendimiento
5. ✅ **Eficiencia**: Procesamiento optimizado con límites de tiempo y recursos
6. ✅ **Mantenibilidad**: Código modular y fácil de mantener

---

## 🏗️ Arquitectura Propuesta

### Opción 1: Sistema de Cola con Procesamiento por Lotes (RECOMENDADA)

```
┌─────────────────────────────────────────────────────────────┐
│                    CRON JOB (Cada 5 minutos)                  │
│              tickets/cron/email_processor.php                │
└──────────────────────┬────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. Obtener configuraciones activas                         │
│ 2. Verificar última ejecución                              │
│ 3. Procesar empresas pendientes (máx. 3 por ejecución)     │
└──────────────────────┬────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Por cada empresa (con try-catch):              │
│  ┌────────────────────────────────────────────┐            │
│  │ • Conectar IMAP                           │            │
│  │ • Obtener correos (máx. 50 por ejecución) │            │
│  │ • Procesar correos                        │            │
│  │ • Registrar éxito/error                   │            │
│  └────────────────────────────────────────────┘            │
└─────────────────────────────────────────────────────────────┘
```

**Ventajas**:
- ✅ Procesamiento controlado y escalable
- ✅ No bloquea el sistema
- ✅ Fácil de monitorear
- ✅ Permite reintentos automáticos
- ✅ Bajo consumo de recursos

**Desventajas**:
- ⚠️ Requiere configuración de cron
- ⚠️ Puede haber delay en procesamiento (máx. 5 minutos)

---

### Opción 2: Sistema de Workers con Base de Datos

```
┌─────────────────────────────────────────────────────────────┐
│                    Tabla: email_processing_queue            │
│  - id_empresa                                               │
│  - status (pending/processing/completed/error)              │
│  - last_processed_at                                        │
│  - next_process_at                                          │
│  - error_count                                              │
│  - last_error                                               │
└──────────────────────┬───────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Worker Script (ejecuta cada 2 min)             │
│  • Selecciona empresas pendientes (máx. 2)                 │
│  • Marca como "processing"                                  │
│  • Procesa emails                                           │
│  • Actualiza estado y métricas                              │
└─────────────────────────────────────────────────────────────┘
```

**Ventajas**:
- ✅ Control granular por empresa
- ✅ Sistema de reintentos inteligente
- ✅ Priorización posible
- ✅ Métricas detalladas

**Desventajas**:
- ⚠️ Requiere nueva tabla en BD
- ⚠️ Más complejo de implementar

---

### Opción 3: Procesamiento Paralelo con Límites (HÍBRIDA)

```
┌─────────────────────────────────────────────────────────────┐
│              Script Principal (ejecuta cada 5 min)          │
│  • Obtiene todas las configuraciones activas                │
│  • Procesa máximo 3 empresas por ejecución                  │
│  • Timeout de 4 minutos por empresa                         │
│  • Registra errores en tabla de logs                        │
└─────────────────────────────────────────────────────────────┘
```

**Ventajas**:
- ✅ Implementación más simple
- ✅ No requiere nueva tabla
- ✅ Control de recursos
- ✅ Fácil de depurar

**Desventajas**:
- ⚠️ Menos granular que Opción 2

---

## 🎯 Solución Recomendada: Opción 3 (Híbrida Mejorada)

### Razones de la Elección

1. **Simplicidad**: No requiere cambios mayores en la arquitectura actual
2. **Efectividad**: Resuelve los problemas principales sin sobre-ingeniería
3. **Mantenibilidad**: Fácil de entender y mantener
4. **Escalabilidad**: Puede evolucionar a Opción 2 si es necesario

---

## 📐 Diseño Detallado

### 1. Nueva Tabla: `email_processing_log`

```sql
CREATE TABLE IF NOT EXISTS `email_processing_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` INT(11) NOT NULL,
  `status` ENUM('success', 'error', 'timeout', 'skipped') NOT NULL,
  `emails_processed` INT(11) DEFAULT 0,
  `emails_found` INT(11) DEFAULT 0,
  `execution_time` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Segundos',
  `error_message` TEXT NULL,
  `last_processed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_empresa_status` (`id_empresa`, `status`),
  INDEX `idx_last_processed` (`id_empresa`, `last_processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito**: Registrar cada ejecución de procesamiento por empresa.

---

### 2. Modificaciones en `empresa_email_config`

```sql
ALTER TABLE `empresa_email_config` 
ADD COLUMN `last_processed_at` TIMESTAMP NULL AFTER `updated_at`,
ADD COLUMN `processing_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `activo`,
ADD COLUMN `max_emails_per_run` INT(11) NOT NULL DEFAULT 50 AFTER `processing_enabled`,
ADD COLUMN `processing_interval_minutes` INT(11) NOT NULL DEFAULT 5 AFTER `max_emails_per_run`,
ADD COLUMN `error_count` INT(11) NOT NULL DEFAULT 0 AFTER `processing_interval_minutes`,
ADD COLUMN `last_error` TEXT NULL AFTER `error_count`,
ADD INDEX `idx_processing_enabled` (`processing_enabled`, `activo`);
```

**Propósito**: Control granular por empresa y configuración de procesamiento.

---

### 3. Nuevo Script Principal: `tickets/cron/email_processor.php`

**Características**:
- ✅ Obtiene configuraciones activas ordenadas por `last_processed_at`
- ✅ Procesa máximo 3 empresas por ejecución
- ✅ Timeout de 4 minutos por empresa
- ✅ Límite de 50 correos por empresa por ejecución
- ✅ Manejo robusto de errores con try-catch por empresa
- ✅ Registra logs en `email_processing_log`
- ✅ Actualiza `last_processed_at` y `error_count`

**Flujo**:
```
1. Obtener empresas activas ordenadas por last_processed_at (ASC)
2. Filtrar empresas que no necesitan procesamiento (según intervalo)
3. Seleccionar máximo 3 empresas
4. Por cada empresa:
   a. Iniciar timer
   b. Try-catch para capturar errores
   c. Conectar IMAP
   d. Obtener correos (máx. 50)
   e. Procesar correos
   f. Registrar éxito en log
   g. Actualizar last_processed_at
   h. Si error: registrar en log y actualizar error_count
5. Cerrar conexiones
```

---

### 4. Modificaciones en `outlook_emails.php`

**Cambios**:
- ✅ Convertir código de ejecución directa a funciones reutilizables
- ✅ Separar lógica de procesamiento de lógica de visualización
- ✅ Extraer función `processEmpresaEmails($id_empresa, $max_emails = 50)`
- ✅ Mantener código HTML para visualización manual (opcional)

---

### 5. Script de Configuración de Cron

**Windows (Task Scheduler)**:
```batch
# Archivo: setup_email_cron.bat
schtasks /create /tn "EmailProcessor" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\tickets\cron\email_processor.php" /sc minute /mo 5 /ru SYSTEM
```

**Linux (Crontab)**:
```bash
# Ejecutar: crontab -e
*/5 * * * * /usr/bin/php /var/www/html/tickets/cron/email_processor.php >> /var/log/email_processor.log 2>&1
```

---

## 📊 Parámetros de Configuración

### Por Defecto (Global)

```php
// tickets/cron/config_email_processor.php
define('EMAIL_PROCESSOR_MAX_EMPRESAS_PER_RUN', 3);
define('EMAIL_PROCESSOR_TIMEOUT_PER_EMPRESA', 240); // 4 minutos
define('EMAIL_PROCESSOR_MAX_EMAILS_PER_EMPRESA', 50);
define('EMAIL_PROCESSOR_DEFAULT_INTERVAL', 5); // minutos
define('EMAIL_PROCESSOR_MAX_ERROR_COUNT', 10); // Deshabilitar después de 10 errores consecutivos
```

### Por Empresa (Configurable)

- `max_emails_per_run`: Máximo de correos a procesar por ejecución
- `processing_interval_minutes`: Intervalo mínimo entre procesamientos
- `processing_enabled`: Habilitar/deshabilitar procesamiento

---

## 🔄 Flujo de Procesamiento Mejorado

### Ejecución Normal

```
1. Cron ejecuta email_processor.php cada 5 minutos
2. Script obtiene empresas activas con processing_enabled = 1
3. Filtra empresas que necesitan procesamiento:
   - last_processed_at < (NOW() - processing_interval_minutes)
   - O last_processed_at IS NULL
4. Selecciona máximo 3 empresas (ordenadas por antigüedad)
5. Por cada empresa:
   a. Inicia timer
   b. Conecta IMAP (con timeout de 30 segundos)
   c. Si conexión exitosa:
      - Obtiene correos UNSEEN (máx. max_emails_per_run)
      - Procesa cada correo
      - Registra éxito
   d. Si error:
      - Registra error en log
      - Incrementa error_count
      - Si error_count >= 10: deshabilita processing_enabled
   e. Actualiza last_processed_at
6. Cierra todas las conexiones
```

### Manejo de Errores

```
Error de Conexión IMAP:
  → Registrar en email_processing_log
  → Incrementar error_count
  → Continuar con siguiente empresa

Error al Procesar Email:
  → Registrar email específico en log
  → Continuar con siguiente email
  → No afecta procesamiento de otros emails

Timeout:
  → Registrar timeout en log
  → Cerrar conexión IMAP
  → Continuar con siguiente empresa

Error Crítico (BD, memoria, etc.):
  → Registrar error crítico
  → Terminar ejecución
  → Enviar alerta (opcional)
```

---

## 📝 Estructura de Archivos Propuesta

```
tickets/
├── cron/
│   ├── email_processor.php          # Script principal (NUEVO)
│   ├── config_email_processor.php   # Configuración (NUEVO)
│   └── email_processor_functions.php # Funciones auxiliares (NUEVO)
│
├── modules/
│   ├── outlook_emails.php           # MODIFICADO (solo funciones)
│   └── get_emails.php               # Sin cambios
│
└── includes/
    └── email_processing_helper.php  # Funciones helper (NUEVO)
```

---

## 🚀 Plan de Implementación

### Fase 1: Preparación (Sin cambios en producción)

1. ✅ Crear tabla `email_processing_log`
2. ✅ Agregar columnas a `empresa_email_config`
3. ✅ Crear script de migración SQL

**Archivos**:
- `sql/add_email_processing_tables.sql`

---

### Fase 2: Refactorización de Código

1. ✅ Extraer funciones de `outlook_emails.php` a módulos reutilizables
2. ✅ Crear `email_processing_helper.php` con funciones comunes
3. ✅ Crear `email_processor_functions.php` con lógica de procesamiento
4. ✅ Mantener compatibilidad con código existente

**Archivos**:
- `tickets/includes/email_processing_helper.php` (NUEVO)
- `tickets/cron/email_processor_functions.php` (NUEVO)
- `tickets/modules/outlook_emails.php` (MODIFICADO)

---

### Fase 3: Script Principal

1. ✅ Crear `email_processor.php` con lógica completa
2. ✅ Implementar manejo de errores robusto
3. ✅ Implementar logging estructurado
4. ✅ Implementar límites de tiempo y recursos

**Archivos**:
- `tickets/cron/email_processor.php` (NUEVO)
- `tickets/cron/config_email_processor.php` (NUEVO)

---

### Fase 4: Configuración y Pruebas

1. ✅ Configurar cron job (Windows Task Scheduler o Linux Crontab)
2. ✅ Probar con 1 empresa
3. ✅ Probar con múltiples empresas
4. ✅ Probar manejo de errores
5. ✅ Verificar logs

**Archivos**:
- `setup_email_cron.bat` (NUEVO - Windows)
- `setup_email_cron.sh` (NUEVO - Linux)

---

### Fase 5: Monitoreo y Optimización

1. ✅ Crear dashboard de monitoreo (opcional)
2. ✅ Ajustar parámetros según rendimiento
3. ✅ Documentar procedimientos

**Archivos**:
- `tickets/modules/configuration/email_processing_status.php` (OPCIONAL)

---

## 🔧 Configuración de Cron

### Windows (Task Scheduler)

**Archivo**: `setup_email_cron.bat`

```batch
@echo off
echo Configurando tarea programada para procesamiento de emails...
schtasks /create /tn "GK_EmailProcessor" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\tickets\cron\email_processor.php" /sc minute /mo 5 /ru SYSTEM /f
echo Tarea configurada correctamente.
pause
```

**Ejecutar como Administrador**

---

### Linux (Crontab)

**Archivo**: `setup_email_cron.sh`

```bash
#!/bin/bash
# Agregar al crontab
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/bin/php /var/www/html/tickets/cron/email_processor.php >> /var/log/email_processor.log 2>&1") | crontab -
echo "Cron job configurado correctamente"
```

**Ejecutar**: `chmod +x setup_email_cron.sh && ./setup_email_cron.sh`

---

## 📈 Métricas y Monitoreo

### Logs Estructurados

Cada ejecución registra:
- Empresa procesada
- Estado (success/error/timeout)
- Emails encontrados
- Emails procesados
- Tiempo de ejecución
- Mensaje de error (si aplica)

### Consultas Útiles

```sql
-- Últimas ejecuciones por empresa
SELECT id_empresa, status, emails_processed, execution_time, last_processed_at, error_message
FROM email_processing_log
ORDER BY last_processed_at DESC
LIMIT 20;

-- Empresas con errores recientes
SELECT id_empresa, COUNT(*) as error_count, MAX(last_processed_at) as last_error
FROM email_processing_log
WHERE status = 'error' AND last_processed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY id_empresa;

-- Rendimiento promedio por empresa
SELECT id_empresa, 
       AVG(emails_processed) as avg_emails,
       AVG(execution_time) as avg_time,
       COUNT(*) as total_runs
FROM email_processing_log
WHERE status = 'success'
GROUP BY id_empresa;
```

---

## ⚠️ Consideraciones Importantes

### Seguridad

1. ✅ Validar permisos de acceso a archivos
2. ✅ Sanitizar inputs de configuración
3. ✅ Proteger contraseñas en logs
4. ✅ Validar conexiones IMAP antes de procesar

### Rendimiento

1. ✅ Límite de correos por ejecución evita saturación
2. ✅ Timeout por empresa evita bloqueos
3. ✅ Procesamiento secuencial controlado (máx. 3 empresas)
4. ✅ Cierre adecuado de conexiones IMAP

### Escalabilidad Futura

Si se necesita más escalabilidad:
1. Implementar Opción 2 (Sistema de Cola)
2. Agregar procesamiento paralelo con workers
3. Implementar Redis para cola distribuida
4. Agregar balanceador de carga

---

## 🧪 Casos de Prueba

### Prueba 1: Procesamiento Normal
- **Escenario**: 3 empresas activas, todas con correos nuevos
- **Resultado Esperado**: Procesa 3 empresas, máximo 50 correos cada una
- **Tiempo Estimado**: < 2 minutos

### Prueba 2: Manejo de Errores
- **Escenario**: 1 empresa con credenciales incorrectas
- **Resultado Esperado**: Registra error, continúa con otras empresas
- **Tiempo Estimado**: < 30 segundos

### Prueba 3: Timeout
- **Escenario**: 1 empresa con servidor IMAP lento
- **Resultado Esperado**: Timeout después de 4 minutos, continúa con siguiente
- **Tiempo Estimado**: ~4 minutos

### Prueba 4: Múltiples Ejecuciones
- **Escenario**: Ejecutar script cada 5 minutos durante 1 hora
- **Resultado Esperado**: Procesa empresas según intervalo configurado
- **Tiempo Estimado**: 1 hora

### Prueba 5: Escalabilidad
- **Escenario**: 10 empresas activas
- **Resultado Esperado**: Procesa 3 por ejecución, rota entre todas
- **Tiempo Estimado**: ~17 minutos para procesar todas (3 empresas cada 5 min)

---

## 📋 Checklist de Implementación

### Pre-Implementación
- [ ] Revisar y aprobar este plan
- [ ] Backup de base de datos
- [ ] Backup de archivos relacionados

### Implementación
- [ ] Crear tabla `email_processing_log`
- [ ] Modificar tabla `empresa_email_config`
- [ ] Crear `email_processing_helper.php`
- [ ] Crear `email_processor_functions.php`
- [ ] Crear `email_processor.php`
- [ ] Crear `config_email_processor.php`
- [ ] Refactorizar `outlook_emails.php`
- [ ] Crear scripts de configuración de cron

### Pruebas
- [ ] Probar con 1 empresa
- [ ] Probar con múltiples empresas
- [ ] Probar manejo de errores
- [ ] Probar timeout
- [ ] Verificar logs

### Despliegue
- [ ] Configurar cron job
- [ ] Monitorear primera ejecución
- [ ] Verificar procesamiento automático
- [ ] Documentar procedimientos

---

## 🎓 Conclusión

Este plan proporciona una solución escalable y robusta para el procesamiento automático de emails en un entorno multi-empresa. La implementación es gradual y permite evolucionar según las necesidades futuras.

**Próximos Pasos**:
1. Revisar y aprobar este plan
2. Implementar Fase 1 (Preparación)
3. Probar en ambiente de desarrollo
4. Desplegar en producción

---

## 📞 Soporte

Para dudas o modificaciones al plan, contactar al equipo de desarrollo.

**Última actualización**: 2025-01-XX

