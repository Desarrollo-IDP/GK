# Plan de Trabajo: Migración a Sistema de Colas para Procesamiento de Emails

## 📋 Índice

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Análisis del Sistema Actual](#análisis-del-sistema-actual)
3. [Comparación de Tecnologías: RabbitMQ vs Redis](#comparación-de-tecnologías-rabbitmq-vs-redis)
4. [Diseño de la Nueva Arquitectura](#diseño-de-la-nueva-arquitectura)
5. [Plan de Implementación](#plan-de-implementación)
6. [Estrategia de Migración](#estrategia-de-migración)
7. [Pruebas y Validación](#pruebas-y-validación)
8. [Documentación](#documentación)
9. [Cronograma Estimado](#cronograma-estimado)
10. [Riesgos y Mitigaciones](#riesgos-y-mitigaciones)
11. [Propuesta de Optimización y Mejora de Rendimiento](#propuesta-de-optimización-y-mejora-de-rendimiento)

---

## 📊 Resumen Ejecutivo

### Objetivo
Migrar el sistema actual de procesamiento de emails (basado en cron jobs síncronos) a un sistema de colas asíncrono que permita:
- Mayor escalabilidad
- Mejor manejo de picos de carga
- Procesamiento paralelo
- Mejor recuperación ante fallos
- Monitoreo y métricas mejoradas

### Beneficios Esperados
- ✅ Procesamiento paralelo de múltiples empresas simultáneamente
- ✅ Mejor uso de recursos del servidor
- ✅ Reducción de timeouts y errores
- ✅ Capacidad de procesar más emails por minuto
- ✅ Mejor aislamiento de errores entre empresas
- ✅ Escalabilidad horizontal (múltiples workers)

### Tecnología Recomendada
**Redis con Redis Queue (RQ)** o **RabbitMQ** - Ver sección de comparación para decisión final.

---

## 🔍 Análisis del Sistema Actual

### Arquitectura Actual

```
┌─────────────────┐
│   CRON Job      │  (Cada 5 minutos)
│ email_processor │
└────────┬────────┘
         │
         ├─► Obtiene 3 empresas pendientes
         │
         ├─► Para cada empresa (secuencial):
         │   ├─► Conecta IMAP (timeout 30s)
         │   ├─► Obtiene emails UNSEEN (máx. 50)
         │   ├─► Procesa cada email (síncrono)
         │   └─► Timeout total: 4 minutos
         │
         └─► Registra logs en BD
```

### Limitaciones Actuales

1. **Procesamiento Secuencial**
   - Solo procesa 3 empresas por ejecución
   - Máximo 50 emails por empresa
   - Timeout de 4 minutos por empresa
   - No hay paralelismo

2. **Recursos Subutilizados**
   - El servidor espera mientras procesa emails
   - No aprovecha múltiples cores/threads
   - Conexiones IMAP mantenidas durante todo el proceso

3. **Escalabilidad Limitada**
   - Aumentar capacidad requiere modificar límites
   - No se puede escalar horizontalmente
   - Un error en una empresa puede afectar otras

4. **Manejo de Errores**
   - Si un email falla, puede afectar el procesamiento
   - No hay reintentos automáticos
   - Errores se registran pero no se recuperan

5. **Monitoreo Limitado**
   - Logs en base de datos
   - No hay métricas en tiempo real
   - Difícil identificar cuellos de botella

### Métricas Actuales

- **Frecuencia de ejecución**: Cada 5 minutos
- **Empresas por ejecución**: Máximo 3
- **Emails por empresa**: Máximo 50
- **Timeout por empresa**: 4 minutos
- **Capacidad teórica**: ~18 empresas/hora, ~900 emails/hora

---

## ⚖️ Comparación de Tecnologías: RabbitMQ vs Redis

### RabbitMQ

#### Ventajas
- ✅ Sistema de mensajería robusto y maduro
- ✅ Garantías de entrega (persistencia, confirmaciones)
- ✅ Soporte para múltiples protocolos (AMQP, MQTT, STOMP)
- ✅ Interfaz web de monitoreo (Management Plugin)
- ✅ Colas prioritarias y routing avanzado
- ✅ Dead Letter Queues para manejo de errores
- ✅ Clustering y alta disponibilidad

#### Desventajas
- ❌ Requiere instalación y configuración adicional
- ❌ Mayor consumo de recursos
- ❌ Curva de aprendizaje más pronunciada
- ❌ Dependencia externa adicional

#### Casos de Uso Ideales
- Sistemas que requieren garantías de entrega estrictas
- Necesidad de routing complejo
- Múltiples tipos de mensajes/colas
- Integración con otros sistemas

### Redis + Redis Queue (RQ)

#### Ventajas
- ✅ Más simple de implementar y mantener
- ✅ Menor consumo de recursos
- ✅ Interfaz web de monitoreo (Flower o RQ Dashboard)
- ✅ Reintentos automáticos configurables
- ✅ Resultados persistentes
- ✅ Buen rendimiento para cargas medianas
- ✅ Ya puede estar instalado en el servidor

#### Desventajas
- ❌ Menos robusto que RabbitMQ para cargas muy altas
- ❌ Garantías de entrega dependen de la configuración
- ❌ Menos opciones de routing avanzado

#### Casos de Uso Ideales
- Sistemas Python/PHP con cargas medianas
- Necesidad de simplicidad
- Procesamiento de tareas asíncronas
- Ya se usa Redis en el proyecto

### Recomendación

**Para este proyecto: Redis + Redis Queue (RQ) o PHP Queue (similar)**

**Razones:**
1. Simplicidad de implementación
2. Menor overhead operacional
3. Suficiente para el volumen actual y futuro previsible
4. Facilita la migración gradual
5. Mejor integración con PHP existente

**Alternativa:** Si se prevé crecimiento significativo o necesidad de garantías más estrictas, considerar RabbitMQ.

---

## 🏗️ Diseño de la Nueva Arquitectura

### Arquitectura Propuesta con Redis/RQ

```
┌─────────────────────────────────────────────────────────────┐
│                    SISTEMA DE COLAS                         │
│                                                             │
│  ┌──────────────┐      ┌──────────────┐                   │
│  │   Producer   │      │    Queue     │                   │
│  │ (email_fetch)│─────►│  (emails)    │                   │
│  └──────────────┘      └──────┬───────┘                   │
│                               │                            │
│                               ▼                            │
│                        ┌──────────────┐                    │
│                        │   Workers    │                    │
│                        │ (processors) │                    │
│                        └──────────────┘                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
         │                              │
         │                              │
         ▼                              ▼
┌─────────────────┐          ┌─────────────────┐
│  Base de Datos  │          │  Redis Server  │
│  (MySQL)        │          │  (Colas)        │
└─────────────────┘          └─────────────────┘
```

### Componentes Principales

#### 1. **Email Fetcher (Producer)**
- **Responsabilidad**: Conectar a IMAP y obtener emails nuevos
- **Frecuencia**: Ejecutado por cron (cada 1-2 minutos)
- **Acción**: Encolar emails en Redis
- **Archivo**: `tickets/cron/email_fetcher.php`

#### 2. **Queue (Redis)**
- **Cola principal**: `email_processing`
- **Cola de reintentos**: `email_processing:retry`
- **Cola de fallidos**: `email_processing:failed`
- **TTL**: Configurable por tipo de email

#### 3. **Email Workers (Consumers)**
- **Responsabilidad**: Procesar emails de la cola
- **Cantidad**: Configurable (ej: 5-10 workers)
- **Archivo**: `tickets/cron/email_worker.php`
- **Ejecución**: Procesos PHP persistentes o supervisados

#### 4. **Monitor/Dashboard**
- **Responsabilidad**: Monitorear colas y workers
- **Herramienta**: RQ Dashboard o Flower (para RQ)
- **Métricas**: Tamaño de cola, workers activos, throughput

### Flujo de Procesamiento

```
1. CRON ejecuta email_fetcher.php cada 1-2 minutos
   │
   ├─► Obtiene empresas activas
   │
   ├─► Para cada empresa:
   │   ├─► Conecta IMAP
   │   ├─► Obtiene emails UNSEEN
   │   ├─► Para cada email:
   │   │   └─► Encola en Redis con metadata:
   │   │       - email_uid
   │   │       - empresa_id
   │   │       - imap_config (encriptado)
   │   │       - timestamp
   │   └─► Cierra conexión IMAP
   │
   └─► Workers procesan en paralelo:
       ├─► Worker 1: Procesa email empresa A
       ├─► Worker 2: Procesa email empresa B
       ├─► Worker 3: Procesa email empresa C
       └─► ...
```

### Estructura de Datos en Cola

```json
{
  "email_uid": 12345,
  "empresa_id": 1,
  "imap_config": {
    "host": "imap.example.com",
    "port": 993,
    "username": "user@example.com",
    "password": "encrypted_password",
    "folder": "INBOX"
  },
  "message_id": "abc123@example.com",
  "date": "2025-01-15 10:30:00",
  "from_email": "sender@example.com",
  "attempts": 0,
  "max_attempts": 3,
  "created_at": "2025-01-15 10:31:00"
}
```

---

## 🛠️ Plan de Implementación

### Fase 1: Preparación e Infraestructura (Semana 1)

#### 1.1 Instalación y Configuración de Redis
- [ ] Verificar/instalar Redis en servidor
- [ ] Configurar Redis para persistencia
- [ ] Configurar límites de memoria
- [ ] Probar conexión desde PHP
- [ ] Documentar configuración

#### 1.2 Instalación de Librerías PHP
- [ ] Instalar `predis/predis` o `phpredis` para PHP
- [ ] Instalar librería de colas (ej: `php-enqueue/enqueue` o `bernard/bernard`)
- [ ] Crear archivo de configuración de colas
- [ ] Probar conexión y operaciones básicas

#### 1.3 Estructura de Base de Datos
- [ ] Crear tabla `email_queue_jobs` (opcional, para tracking)
- [ ] Crear tabla `email_queue_metrics` (para métricas)
- [ ] Migrar/adaptar tabla `email_processing_log`
- [ ] Crear índices necesarios

**Archivos a crear:**
```
tickets/cron/queue_config.php          # Configuración de colas
tickets/cron/queue_manager.php         # Clase para manejo de colas
tickets/sql/create_email_queue_tables.sql
```

### Fase 2: Implementación del Producer (Semana 2)

#### 2.1 Email Fetcher
- [ ] Crear `email_fetcher.php` (reemplaza parte de `email_processor.php`)
- [ ] Implementar lógica de conexión IMAP por empresa
- [ ] Implementar encolado de emails en Redis
- [ ] Agregar manejo de errores y logging
- [ ] Probar con una empresa de prueba

**Funcionalidades:**
- Conectar a IMAP
- Obtener emails UNSEEN
- Validar emails (evitar duplicados)
- Encolar en Redis con metadata
- Cerrar conexión IMAP
- Registrar métricas

**Archivo:** `tickets/cron/email_fetcher.php`

#### 2.2 Queue Manager
- [ ] Crear clase `QueueManager` para operaciones de cola
- [ ] Métodos: `enqueue()`, `dequeue()`, `retry()`, `fail()`
- [ ] Implementar prioridades (opcional)
- [ ] Implementar TTL para jobs
- [ ] Agregar logging y métricas

**Archivo:** `tickets/cron/queue_manager.php`

### Fase 3: Implementación de Workers (Semana 3)

#### 3.1 Email Worker
- [ ] Crear `email_worker.php` (procesa emails de la cola)
- [ ] Implementar loop de procesamiento
- [ ] Integrar función `processEmail()` existente
- [ ] Implementar manejo de errores y reintentos
- [ ] Agregar señales para shutdown graceful

**Funcionalidades:**
- Desencolar email de Redis
- Conectar a IMAP con configuración del email
- Procesar email usando `processEmail()`
- Manejar éxito/error
- Reintentos automáticos
- Registrar métricas

**Archivo:** `tickets/cron/email_worker.php`

#### 3.2 Worker Supervisor
- [ ] Crear script para ejecutar múltiples workers
- [ ] Implementar gestión de procesos
- [ ] Agregar auto-restart en caso de fallo
- [ ] Configurar límites de memoria/CPU

**Archivo:** `tickets/cron/worker_supervisor.php`

### Fase 4: Monitoreo y Dashboard (Semana 4)

#### 4.1 Dashboard de Colas
- [ ] Instalar/configurar RQ Dashboard o similar
- [ ] Configurar acceso y seguridad
- [ ] Agregar métricas personalizadas
- [ ] Crear alertas básicas

#### 4.2 Métricas y Logging
- [ ] Implementar registro de métricas en BD
- [ ] Crear vistas/dashboards en sistema
- [ ] Agregar alertas por email/SMS (opcional)
- [ ] Documentar métricas disponibles

**Archivos:**
```
tickets/cron/queue_metrics.php         # Recolección de métricas
tickets/modules/queue_dashboard.php    # Dashboard interno (opcional)
```

### Fase 5: Migración y Pruebas (Semana 5)

#### 5.1 Pruebas Unitarias
- [ ] Probar encolado/desencolado
- [ ] Probar procesamiento de emails
- [ ] Probar manejo de errores
- [ ] Probar reintentos

#### 5.2 Pruebas de Integración
- [ ] Probar con empresa de prueba
- [ ] Probar con múltiples empresas
- [ ] Probar con picos de carga
- [ ] Probar recuperación ante fallos

#### 5.3 Pruebas de Carga
- [ ] Simular 100+ emails
- [ ] Medir throughput
- [ ] Identificar cuellos de botella
- [ ] Optimizar según resultados

### Fase 6: Migración Gradual (Semana 6)

#### 6.1 Modo Dual (Paralelo)
- [ ] Mantener sistema actual funcionando
- [ ] Activar nuevo sistema en paralelo
- [ ] Comparar resultados
- [ ] Ajustar configuración

#### 6.2 Migración por Empresas
- [ ] Migrar 1-2 empresas al nuevo sistema
- [ ] Monitorear durante 1 semana
- [ ] Migrar más empresas gradualmente
- [ ] Desactivar sistema antiguo

#### 6.3 Limpieza
- [ ] Desactivar cron antiguo
- [ ] Eliminar código obsoleto
- [ ] Actualizar documentación
- [ ] Capacitar equipo

---

## 🔄 Estrategia de Migración

### Opción 1: Migración Gradual (Recomendada)

**Ventajas:**
- Menor riesgo
- Permite comparar resultados
- Rollback fácil

**Pasos:**
1. Implementar nuevo sistema en paralelo
2. Migrar empresas de prueba (10-20%)
3. Monitorear durante 1 semana
4. Migrar más empresas gradualmente
5. Desactivar sistema antiguo

### Opción 2: Migración Completa

**Ventajas:**
- Más rápido
- Menos complejidad temporal

**Desventajas:**
- Mayor riesgo
- Rollback más difícil

**Cuándo usar:**
- Sistema pequeño
- Ventana de mantenimiento disponible
- Equipo experimentado

### Plan de Rollback

1. **Preparación:**
   - Mantener sistema antiguo funcionando
   - Documentar configuración actual
   - Backup de base de datos

2. **Activación de Rollback:**
   - Desactivar nuevo sistema
   - Reactivar cron antiguo
   - Verificar procesamiento
   - Notificar equipo

3. **Post-Rollback:**
   - Analizar causa del problema
   - Corregir en nuevo sistema
   - Reintentar migración

---

## ✅ Pruebas y Validación

### Checklist de Pruebas

#### Funcionalidad Básica
- [ ] Email se encola correctamente
- [ ] Email se procesa correctamente
- [ ] Ticket se crea/actualiza correctamente
- [ ] Adjuntos se guardan correctamente
- [ ] Logs se registran correctamente

#### Manejo de Errores
- [ ] Error de conexión IMAP se maneja
- [ ] Email inválido no rompe el sistema
- [ ] Reintentos funcionan correctamente
- [ ] Emails fallidos se registran

#### Rendimiento
- [ ] Throughput mayor que sistema actual
- [ ] Latencia aceptable (< 30 segundos)
- [ ] Uso de recursos razonable
- [ ] No hay memory leaks

#### Escalabilidad
- [ ] Múltiples workers funcionan correctamente
- [ ] No hay condiciones de carrera
- [ ] Balanceo de carga funciona
- [ ] Sistema maneja picos de carga

#### Integración
- [ ] Compatible con sistema actual
- [ ] No rompe funcionalidades existentes
- [ ] Métricas se registran correctamente
- [ ] Dashboard muestra información correcta

### Criterios de Aceptación

1. **Funcionalidad:**
   - 100% de emails procesados correctamente
   - 0% de pérdida de datos
   - Compatibilidad con sistema actual

2. **Rendimiento:**
   - Throughput ≥ 2x sistema actual
   - Latencia < 30 segundos promedio
   - Uso de CPU < 70%
   - Uso de memoria estable

3. **Confiabilidad:**
   - Uptime ≥ 99.5%
   - Reintentos automáticos funcionan
   - Recuperación ante fallos < 5 minutos

---

## 📚 Documentación

### Documentos a Crear

1. **Documentación Técnica**
   - [ ] Arquitectura del sistema
   - [ ] Guía de instalación
   - [ ] Guía de configuración
   - [ ] API de colas
   - [ ] Troubleshooting

2. **Documentación Operacional**
   - [ ] Guía de monitoreo
   - [ ] Procedimientos de mantenimiento
   - [ ] Plan de escalamiento
   - [ ] Procedimientos de rollback

3. **Documentación para Desarrolladores**
   - [ ] Cómo agregar nuevos tipos de jobs
   - [ ] Cómo depurar workers
   - [ ] Estructura de código
   - [ ] Convenciones de código

### Archivos de Documentación

```
docs/
├── arquitectura_colas.md
├── guia_instalacion.md
├── guia_configuracion.md
├── guia_monitoreo.md
├── troubleshooting.md
└── api_colas.md
```

---

## 📅 Cronograma Estimado

### Resumen

| Fase | Duración | Descripción |
|------|----------|-------------|
| Fase 1 | 1 semana | Preparación e Infraestructura |
| Fase 2 | 1 semana | Implementación del Producer |
| Fase 3 | 1 semana | Implementación de Workers |
| Fase 4 | 1 semana | Monitoreo y Dashboard |
| Fase 5 | 1 semana | Pruebas y Validación |
| Fase 6 | 1 semana | Migración Gradual |
| **Total** | **6 semanas** | **~1.5 meses** |

### Hitos Principales

- **Semana 2**: Producer funcionando
- **Semana 3**: Workers funcionando
- **Semana 4**: Sistema completo con monitoreo
- **Semana 5**: Pruebas completadas
- **Semana 6**: Migración completada

### Consideraciones

- Tiempo puede variar según complejidad encontrada
- Buffer de 1-2 semanas recomendado para imprevistos
- Pruebas adicionales pueden requerir más tiempo

---

## ⚠️ Riesgos y Mitigaciones

### Riesgos Técnicos

#### 1. Pérdida de Emails
**Riesgo:** Alto  
**Probabilidad:** Media  
**Mitigación:**
- Implementar persistencia en Redis
- Backup de colas antes de procesar
- Logs detallados de todos los emails
- Sistema de dead letter queue

#### 2. Problemas de Rendimiento
**Riesgo:** Medio  
**Probabilidad:** Media  
**Mitigación:**
- Pruebas de carga antes de migración
- Monitoreo continuo
- Escalabilidad horizontal preparada
- Optimización de queries

#### 3. Incompatibilidad con Sistema Actual
**Riesgo:** Medio  
**Probabilidad:** Baja  
**Mitigación:**
- Pruebas exhaustivas de integración
- Modo dual durante migración
- Rollback plan preparado

### Riesgos Operacionales

#### 1. Falta de Conocimiento del Equipo
**Riesgo:** Medio  
**Probabilidad:** Media  
**Mitigación:**
- Capacitación del equipo
- Documentación detallada
- Sesiones de práctica
- Soporte durante migración

#### 2. Tiempo de Migración Extendido
**Riesgo:** Bajo  
**Probabilidad:** Media  
**Mitigación:**
- Cronograma realista con buffer
- Priorización de funcionalidades
- Migración gradual

### Plan de Contingencia

1. **Problemas Críticos:**
   - Rollback inmediato al sistema antiguo
   - Análisis post-mortem
   - Corrección y reintento

2. **Problemas Menores:**
   - Corrección en caliente
   - Documentación del problema
   - Mejora continua

---

## 🚀 Propuesta de Optimización y Mejora de Rendimiento

### Objetivo
Reducir significativamente el tiempo de procesamiento de correos mediante optimizaciones en múltiples niveles, logrando un procesamiento **5-10x más rápido** que el sistema actual.

### Análisis de Cuellos de Botella Actuales

#### 1. Conexiones IMAP
- **Problema**: Cada empresa requiere conexión IMAP separada
- **Impacto**: 2-5 segundos por conexión
- **Solución**: Pool de conexiones IMAP reutilizables

#### 2. Procesamiento Secuencial
- **Problema**: Emails procesados uno por uno
- **Impacto**: Tiempo total = suma de tiempos individuales
- **Solución**: Procesamiento paralelo con workers múltiples

#### 3. Operaciones de Base de Datos
- **Problema**: Múltiples queries por email
- **Impacto**: 0.5-2 segundos por email
- **Solución**: Batch inserts, prepared statements optimizados, índices

#### 4. Procesamiento de Adjuntos
- **Problema**: Descarga y guardado secuencial
- **Impacto**: 1-10 segundos por adjunto
- **Solución**: Procesamiento asíncrono de adjuntos

#### 5. Validaciones y Verificaciones
- **Problema**: Múltiples verificaciones redundantes
- **Impacto**: 0.1-0.5 segundos por email
- **Solución**: Cache de verificaciones, validaciones optimizadas

### Estrategias de Optimización

#### 1. Pool de Conexiones IMAP

**Implementación:**
```php
class ImapConnectionPool {
    private $connections = [];
    private $max_connections = 10;
    
    public function getConnection($empresa_id, $config) {
        $key = "empresa_{$empresa_id}";
        
        if (!isset($this->connections[$key])) {
            $this->connections[$key] = $this->createConnection($config);
        }
        
        return $this->connections[$key];
    }
    
    // Reutilizar conexiones activas
    // Timeout de inactividad: 5 minutos
}
```

**Beneficios:**
- ✅ Reducción de 2-5 segundos por empresa
- ✅ Menor carga en servidor IMAP
- ✅ Mejor manejo de límites de conexión

**Ahorro estimado:** 30-50% del tiempo de conexión

#### 2. Procesamiento Paralelo con Workers Múltiples

**Configuración Recomendada:**
- **Workers por empresa**: 2-3 workers dedicados
- **Workers generales**: 5-10 workers compartidos
- **Priorización**: Emails nuevos > Respuestas > Actualizaciones

**Arquitectura:**
```
┌─────────────────────────────────────┐
│         Email Queue (Redis)         │
└──────────────┬───────────────────────┘
               │
       ┌───────┴────────┐
       │                │
   ┌───▼───┐      ┌─────▼─────┐
   │Worker │      │  Worker   │
   │   1   │      │     2     │
   └───┬───┘      └─────┬─────┘
       │                │
   ┌───▼───┐      ┌─────▼─────┐
   │Worker │      │  Worker   │
   │   3   │      │     4     │
   └───────┘      └───────────┘
```

**Beneficios:**
- ✅ Procesamiento simultáneo de múltiples emails
- ✅ Mejor uso de CPU multi-core
- ✅ Throughput 5-10x mayor

**Ahorro estimado:** 80-90% del tiempo total de procesamiento

#### 3. Optimización de Base de Datos

**A. Batch Inserts**
```php
// En lugar de:
foreach ($emails as $email) {
    $stmt->execute(); // 1 query por email
}

// Usar:
$stmt->bind_param(...);
foreach ($emails as $email) {
    // Preparar datos
}
$stmt->execute(); // 1 query para múltiples emails
```

**B. Índices Optimizados**
```sql
-- Índices para búsquedas frecuentes
CREATE INDEX idx_message_id_empresa ON processed_emails(message_id, id_empresa);
CREATE INDEX idx_email_uid_empresa ON processed_emails(email_uid, id_empresa);
CREATE INDEX idx_ticket_requester ON tickets(requester_id, id_empresa);
```

**C. Queries Optimizadas**
- Usar `INSERT ... ON DUPLICATE KEY UPDATE` para evitar SELECT + INSERT
- Usar transacciones para operaciones relacionadas
- Limitar campos en SELECT (no usar SELECT *)

**Beneficios:**
- ✅ Reducción de 50-70% en tiempo de BD
- ✅ Menor carga en servidor MySQL
- ✅ Mejor escalabilidad

**Ahorro estimado:** 40-60% del tiempo de operaciones BD

#### 4. Procesamiento Asíncrono de Adjuntos

**Estrategia:**
1. Procesar email inmediatamente (sin adjuntos)
2. Encolar adjuntos en cola separada
3. Procesar adjuntos en background

**Implementación:**
```php
// Procesar email principal
processEmail($email_uid);

// Encolar adjuntos
foreach ($attachments as $attachment) {
    $queue->enqueue('attachment_processing', [
        'email_id' => $email_id,
        'attachment_data' => $attachment
    ]);
}
```

**Beneficios:**
- ✅ Respuesta inmediata al usuario
- ✅ Procesamiento paralelo de adjuntos
- ✅ No bloquea procesamiento de otros emails

**Ahorro estimado:** 60-80% del tiempo para emails con adjuntos grandes

#### 5. Sistema de Cache Inteligente

**Cache de Verificaciones:**
```php
// Cache Redis para verificaciones frecuentes
- Verificación de emails procesados (TTL: 1 hora)
- Verificación de blacklist (TTL: 30 minutos)
- Configuración de empresas (TTL: 5 minutos)
- Mapeo de requesters (TTL: 15 minutos)
```

**Cache de Configuraciones:**
- Configuraciones IMAP por empresa
- Configuraciones de email por empresa
- Listas de blacklist

**Beneficios:**
- ✅ Reducción de queries a BD
- ✅ Respuesta instantánea para verificaciones
- ✅ Menor carga en servidor

**Ahorro estimado:** 20-30% del tiempo de verificaciones

#### 6. Optimización de Parsing de Emails

**Estrategia:**
- Usar parser más eficiente (MailMimeParser ya está optimizado)
- Cache de estructuras parseadas
- Procesamiento lazy de partes no críticas

**Mejoras:**
```php
// Parsear solo lo necesario inicialmente
$parser->parse($raw_email);
$subject = $parser->getHeader('subject'); // Inmediato
$body = $parser->getHtmlContent(); // Solo si es necesario
$attachments = $parser->getAttachments(); // Solo si hay adjuntos
```

**Ahorro estimado:** 10-20% del tiempo de parsing

#### 7. Priorización Inteligente

**Sistema de Prioridades:**
```
Prioridad 1 (Alta): Emails nuevos de clientes VIP
Prioridad 2 (Media): Emails nuevos normales
Prioridad 3 (Baja): Respuestas a tickets existentes
Prioridad 4 (Muy Baja): Actualizaciones y notificaciones
```

**Implementación:**
- Colas separadas por prioridad
- Workers dedicados para alta prioridad
- Balanceo automático

**Beneficios:**
- ✅ Respuesta rápida a emails críticos
- ✅ Mejor experiencia de usuario
- ✅ Optimización de recursos

#### 8. Procesamiento por Lotes (Batching)

**Estrategia:**
- Agrupar emails de la misma empresa
- Procesar en lotes de 10-20 emails
- Reducir overhead de conexiones

**Implementación:**
```php
// Agrupar emails por empresa
$emails_by_empresa = [];
foreach ($emails as $email) {
    $emails_by_empresa[$email['empresa_id']][] = $email;
}

// Procesar por lotes
foreach ($emails_by_empresa as $empresa_id => $empresa_emails) {
    processBatch($empresa_id, $empresa_emails);
}
```

**Beneficios:**
- ✅ Menos conexiones IMAP
- ✅ Menos overhead de transacciones
- ✅ Mejor uso de recursos

**Ahorro estimado:** 15-25% del tiempo total

### Métricas de Rendimiento Esperadas

#### Sistema Actual
- **Emails por minuto**: ~15-20 emails
- **Tiempo promedio por email**: 3-5 segundos
- **Throughput máximo**: ~900 emails/hora
- **Latencia**: 5-10 minutos (espera de cron)

#### Sistema Optimizado
- **Emails por minuto**: **100-200 emails** (5-10x)
- **Tiempo promedio por email**: **0.5-1 segundo** (5-10x más rápido)
- **Throughput máximo**: **6,000-12,000 emails/hora** (6-13x)
- **Latencia**: **< 30 segundos** (10-20x más rápido)

### Plan de Implementación de Optimizaciones

#### Fase 1: Optimizaciones Básicas (Semana 1)
- [ ] Implementar pool de conexiones IMAP
- [ ] Optimizar queries de base de datos
- [ ] Agregar índices necesarios
- [ ] Implementar cache básico

**Mejora esperada:** 2-3x más rápido

#### Fase 2: Procesamiento Paralelo (Semana 2)
- [ ] Configurar múltiples workers
- [ ] Implementar balanceo de carga
- [ ] Agregar sistema de prioridades
- [ ] Optimizar procesamiento de adjuntos

**Mejora esperada:** 5-7x más rápido

#### Fase 3: Optimizaciones Avanzadas (Semana 3)
- [ ] Procesamiento asíncrono de adjuntos
- [ ] Cache inteligente avanzado
- [ ] Procesamiento por lotes
- [ ] Monitoreo y ajuste fino

**Mejora esperada:** 8-10x más rápido

### Configuración Recomendada de Workers

#### Configuración Inicial (Conservadora)
```ini
# Configuración de Workers
workers.general = 5
workers.high_priority = 2
workers.attachment = 3
max_concurrent_per_worker = 1
memory_limit_per_worker = 256M
```

#### Configuración Optimizada (Producción)
```ini
# Configuración de Workers
workers.general = 10
workers.high_priority = 3
workers.attachment = 5
max_concurrent_per_worker = 2
memory_limit_per_worker = 512M
```

#### Configuración Máxima (Alta Carga)
```ini
# Configuración de Workers
workers.general = 20
workers.high_priority = 5
workers.attachment = 10
max_concurrent_per_worker = 3
memory_limit_per_worker = 1G
```

### Monitoreo de Rendimiento

#### Métricas Clave a Monitorear

1. **Throughput**
   - Emails procesados por minuto
   - Emails procesados por hora
   - Tasa de éxito/fallo

2. **Latencia**
   - Tiempo desde recepción hasta procesamiento
   - Tiempo de procesamiento por email
   - Tiempo en cola

3. **Recursos**
   - Uso de CPU por worker
   - Uso de memoria por worker
   - Conexiones IMAP activas
   - Conexiones a BD activas

4. **Colas**
   - Tamaño de cola principal
   - Tamaño de cola de adjuntos
   - Tiempo promedio en cola
   - Tasa de crecimiento

#### Dashboard de Rendimiento

```php
// Métricas en tiempo real
- Emails/minuto actual
- Emails/hora acumulado
- Tiempo promedio de procesamiento
- Workers activos/inactivos
- Tamaño de colas
- Tasa de errores
```

### Optimizaciones Adicionales (Futuro)

#### 1. Machine Learning para Priorización
- Clasificar emails por importancia
- Predecir tiempo de procesamiento
- Optimizar asignación de workers

#### 2. Procesamiento Distribuido
- Múltiples servidores
- Balanceo de carga entre servidores
- Alta disponibilidad

#### 3. Compresión de Datos
- Comprimir emails en cola
- Reducir uso de memoria
- Acelerar transferencia

#### 4. Procesamiento Incremental
- Procesar solo cambios
- Sincronización incremental
- Reducir carga

### Resumen de Mejoras Esperadas

| Optimización | Mejora | Impacto |
|--------------|--------|---------|
| Pool de Conexiones IMAP | 30-50% | Medio |
| Procesamiento Paralelo | 500-1000% | **Muy Alto** |
| Optimización BD | 40-60% | Alto |
| Adjuntos Asíncronos | 60-80% | Alto |
| Sistema de Cache | 20-30% | Medio |
| Priorización | 30-50% | Medio |
| Procesamiento por Lotes | 15-25% | Bajo-Medio |
| **TOTAL COMBINADO** | **800-1000%** | **Muy Alto** |

### Conclusión de Optimizaciones

Con la implementación de todas estas optimizaciones, se espera lograr:

✅ **Procesamiento 8-10x más rápido**  
✅ **Throughput de 6,000-12,000 emails/hora**  
✅ **Latencia < 30 segundos**  
✅ **Mejor uso de recursos del servidor**  
✅ **Escalabilidad horizontal**  
✅ **Mejor experiencia de usuario**

---

## 📝 Notas Adicionales

### Consideraciones de Seguridad

- [ ] Encriptar credenciales IMAP en cola
- [ ] Autenticación para acceso a Redis
- [ ] Logs sin información sensible
- [ ] Auditoría de acceso

### Consideraciones de Mantenimiento

- [ ] Limpieza automática de colas antiguas
- [ ] Rotación de logs
- [ ] Monitoreo de espacio en disco
- [ ] Actualizaciones de seguridad

### Mejoras Futuras

- [ ] Priorización de emails
- [ ] Procesamiento por prioridad
- [ ] Integración con otros sistemas
- [ ] Machine Learning para clasificación
- [ ] Análisis predictivo de carga

---

## 🎯 Conclusión

Este plan proporciona una hoja de ruta completa para migrar el sistema de procesamiento de emails a un sistema de colas. La implementación debe ser gradual y cuidadosa, priorizando la estabilidad y confiabilidad del sistema.

**Próximos Pasos Inmediatos:**
1. Revisar y aprobar este plan
2. Decidir entre Redis/RQ o RabbitMQ
3. Asignar recursos del equipo
4. Iniciar Fase 1: Preparación e Infraestructura

---

**Versión:** 1.1  
**Fecha:** 2025-01-15  
**Última actualización:** 2025-01-15  
**Autor:** Sistema de Tickets  
**Estado:** Propuesta (con optimizaciones)
