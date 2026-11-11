# Sistema de Cobro por Cancelación de Membresías

## Descripción General

Este sistema implementa un mecanismo automático para cobrar cargos por cancelación de suscripciones cuando un usuario cancela su membresía en MercadoPago. El sistema funciona de la siguiente manera:

1. **Configuración**: Al crear un tipo de membresía, se puede configurar un monto de cancelación
2. **Detección**: El webhook detecta automáticamente las cancelaciones desde MercadoPago
3. **Cobro Automático**: Se procesa automáticamente el cargo usando el token de tarjeta guardado del cliente
4. **Registro**: Se registra todo el proceso en logs y base de datos

## Componentes del Sistema

### 1. Formulario de Tipos de Membresía (`modules/tipos_membresia/add.php`)

- **Campo `monto_cancelacion`**: Permite configurar el monto que se cobrará por cancelación
- **Validación**: Asegura que el monto no sea negativo
- **Almacenamiento**: Se guarda en la base de datos junto con la información del plan

### 2. Webhook (`webhook.php`)

- **Detección de Cancelaciones**: Procesa eventos de tipo `subscription_cancelled` y `preapproval`
- **Función `handleCancellationEvent`**: Maneja la lógica principal de cancelación
- **Función `handleCancellationWithFee`**: Procesa el cobro automático

### 3. Configuración de MercadoPago (`config/mercadopago_config.php`)

- **`mpCreateCancellationPaymentFromClient`**: Crea pagos usando tokens guardados
- **`mpCreateCancellationPayment`**: Crea pagos con tokens directos
- **Manejo de errores**: Logging detallado para debugging

## Flujo de Funcionamiento

### Paso 1: Configuración del Tipo de Membresía
```
Usuario crea tipo de membresía → Configura monto_cancelacion → Se guarda en BD
```

### Paso 2: Cancelación de Suscripción
```
Usuario cancela en MercadoPago → Webhook recibe notificación → Se identifica membresía
```

### Paso 3: Procesamiento del Cargo
```
Sistema verifica monto_cancelacion → Busca token de tarjeta → Procesa pago automático
```

### Paso 4: Registro y Notificación
```
Pago se registra en logs → Se actualiza membresía → Se envía email de confirmación
```

## Estructura de Base de Datos

### Tabla `tipos_membresia`
```sql
monto_cancelacion DECIMAL(10,2) DEFAULT 0.00
-- Monto que se cobrará por cancelación (0 = sin cargo)
```

### Tabla `membresias`
```sql
mp_cancellation_fee DECIMAL(10,2) DEFAULT 0.00
-- Monto del cargo por cancelación aplicado
mp_cancelled_at DATETIME
-- Fecha y hora de la cancelación
mp_cancellation_reason TEXT
-- Razón de la cancelación y detalles del cargo
```

### Tabla `mp_payment_logs`
```sql
-- Registra todos los pagos por cancelación procesados
payment_method VARCHAR(50) -- 'cancellation_fee'
```

## Configuración Requerida

### 1. MercadoPago
- Credenciales válidas (ACCESS_TOKEN, PUBLIC_KEY)
- Webhook configurado para recibir notificaciones
- Permisos para crear pagos

### 2. Base de Datos
- Campo `monto_cancelacion` en tabla `tipos_membresia`
- Campo `mp_cancellation_fee` en tabla `membresias`
- Tabla `mp_payment_logs` para registro de pagos

### 3. Clientes
- Token de tarjeta guardado (`mp_card_token`)
- Customer ID de MercadoPago (`mp_customer_id`)

## Casos de Uso

### Caso 1: Cancelación con Cargo
```
Cliente cancela membresía → Sistema detecta monto_cancelacion > 0
→ Busca token de tarjeta → Procesa pago automático → Registra en logs
```

### Caso 2: Cancelación sin Cargo
```
Cliente cancela membresía → Sistema detecta monto_cancelacion = 0
→ Solo marca como cancelada → No procesa pago
```

### Caso 3: Cliente sin Token de Tarjeta
```
Cliente cancela membresía → Sistema no encuentra token
→ Marca como "Cargo pendiente" → Requiere intervención manual
```

## Manejo de Errores

### Errores Comunes
1. **Cliente sin token de tarjeta**: Se registra como "Cargo pendiente"
2. **Error de API de MercadoPago**: Se loguea el error completo
3. **Error de base de datos**: Se registra en logs del sistema

### Logging
- **Archivo**: `logs/mercadopago_webhook.log`
- **Nivel**: Detallado con contexto completo
- **Retención**: Configurable según necesidades

## Pruebas del Sistema

### Archivo de Prueba
`test_cancellation_fee.php` - Verifica el funcionamiento del sistema

### Comandos de Prueba
```bash
# Verificar funciones disponibles
php test_cancellation_fee.php

# Ejecutar prueba real de cobro
php test_cancellation_fee.php?test_payment=1
```

## Monitoreo y Mantenimiento

### Métricas a Monitorear
- Número de cancelaciones procesadas
- Tasa de éxito en cobros automáticos
- Errores en el procesamiento
- Tiempo de respuesta del webhook

### Tareas de Mantenimiento
- Revisar logs de errores regularmente
- Verificar credenciales de MercadoPago
- Monitorear pagos pendientes de procesar
- Actualizar montos de cancelación según políticas

## Seguridad

### Consideraciones
- Los tokens de tarjeta se almacenan de forma segura
- Solo se procesan pagos por cancelaciones confirmadas
- Se valida la autenticidad de las notificaciones de MercadoPago
- Se registra toda actividad para auditoría

### Recomendaciones
- Usar HTTPS para todas las comunicaciones
- Implementar rate limiting en el webhook
- Validar IPs de MercadoPago
- Mantener logs de auditoría

## Troubleshooting

### Problema: No se procesan cancelaciones
**Solución**: Verificar que el webhook esté configurado correctamente en MercadoPago

### Problema: Errores de API
**Solución**: Verificar credenciales y permisos de MercadoPago

### Problema: Clientes sin token de tarjeta
**Solución**: Implementar proceso de recolección de tokens o cobro manual

### Problema: Pagos duplicados
**Solución**: Verificar lógica de deduplicación en `mp_payment_logs`

## Contacto y Soporte

Para soporte técnico o preguntas sobre el sistema:
- Revisar logs del sistema
- Verificar configuración de MercadoPago
- Consultar documentación de la API
- Contactar al equipo de desarrollo

---

**Última actualización**: <?= date('Y-m-d H:i:s') ?>
**Versión del sistema**: 1.0
**Compatibilidad**: PHP 8.0+, MercadoPago SDK v3
